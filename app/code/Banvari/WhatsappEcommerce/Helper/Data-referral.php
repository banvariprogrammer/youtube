<?php
namespace Apparel\Referral\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    const MODULE_NAME = 'referral/';
    const CREATE_REFERRAL_COUPON_URI = 'promoservice/referral/referralCoupon';
    const UPDATE_REFERRAL_ORDER_URI = 'promoservice/referral/referralStatusUpdate';

    /**
     * @var \Magento\Framework\HTTP\Adapter\CurlFactory
     */
    protected $_curlFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    protected $logger;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\HTTP\Adapter\CurlFactory $curlFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->_curlFactory = $curlFactory;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->customerRepository = $customerRepository;
        parent::__construct($context);

        $this->writer = new \Zend\Log\Writer\Stream(BP . '/var/log/referral.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($this->writer);
    }

    /**
     * Curl call to Referral microservice.
     *
     * @return array
     */
    public function curlToMicroservice($request, $body, $method = 'GET', $storeId = null)
    {
        $baseUrl = $this->scopeConfig->getValue(self::MODULE_NAME.'microservice/base_url',
            ScopeInterface::SCOPE_STORE, 
            $storeId
        );

        $this->logData('Request From Magento To MS');
        
        $requestUrl = $baseUrl . $request;
        $this->logData('URL: ' . $requestUrl);
        $this->logData('Request Data : ' . $body);

        $http = $this->_curlFactory->create();
        $http->setConfig(['header' => false]);
        $headerData = ["Content-Type:application/json", "User-Agent:Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1"];

        if ($method == 'POST') {
            $http->write(\Zend_Http_Client::POST, $requestUrl, '1.1', $headerData, $body);
        } else if($method == 'PUT') {
            $http->write(\Zend_Http_Client::PUT, $requestUrl, '1.1', $headerData, $body); 
        } else {
            $http->write(\Zend_Http_Client::GET, $requestUrl, '1.1', $headerData);
        }

        $responseBody = $http->read();
        $this->logData('Response From MS :' . $responseBody);

        return $responseBody;
    }

    public function logData($data)
    {
        if (gettype($data) == "string") {
            $this->logger->info($data);
        } else {
            $this->logger->info(print_r($data, 1));
        }
    }

    public function getStoreIdsByWebsiteId($websiteId)
    {
        return $this->_storeManager->getWebsite($websiteId)->getStoreIds();
    }

    public function getModuleConfig($path)
    {
        return $this->_scopeConfig->getValue(self::MODULE_NAME.$path, ScopeInterface::SCOPE_STORE);
    }

    public function isEnabled()
    {
        return (bool) $this->getModuleConfig('general/is_enabled');
    }

    public function getMicroserviceUri()
    {
        return $this->getModuleConfig('microservice/base_url');
    }

    public function generateReferralCode($customer) {
        $resp = [];
        try {
            $storeIds = $this->getStoreIdsByWebsiteId($customer->getWebsiteId());
            $body = [
                'userFirstName' => $customer->getFirstname(),
                'userLastName' => $customer->getLastname(),
                'userMiddleName' => $customer->getMiddlename(),
                'email' => $customer->getEmail(),
                'userPhone' => $customer->getContactNo(),
                'storeId' => !empty($storeIds) ? array_values($storeIds) : [],
                'customerId' => $customer->getId()
            ];

            $data = json_encode($body);

            $response = $this->curlToMicroservice(self::CREATE_REFERRAL_COUPON_URI, $data, 'PUT');

            if (!empty($response)) {
                $result = json_decode($response, true);

                if (isset($result['referralCouponCode'])) {
                    $customerData = $customer->getDataModel();
                    $customerData->setCustomAttribute('referral_coupon', $result['referralCouponCode']);
                    $this->customerRepository->save($customerData);

                    $resp['referralCoupon'] = $result['referralCouponCode'];
                }
            }
        } catch (\Exception $e) {
            $this->logData($e->getMessage());
        }
        return $resp;
    }

    public function updateOrderStatusInMS($orderNo, $status, $couponCode, $quoteId) {
        $response = '';
        $referralChar = substr($couponCode,0,4);
        $prefixChar = $this->getModuleConfig('general/prefix_char');
        if($referralChar == $prefixChar) {
            $body = [
                'orderNo' => $orderNo,
                'orderStatus' => $status,
                'referralCode' => $couponCode,
                'cartId' => $quoteId
            ];

            $data = json_encode($body);

            $response = $this->curlToMicroservice(self::UPDATE_REFERRAL_ORDER_URI, $data, 'POST');
        }
        return $response;
    }

    public function updateStatusToMS($order) {
        $orderQty = $cancelQty = $shipQty = $shippedAmt = 0;
        foreach ($order->getAllVisibleItems() as $item) {
            $orderQty += $item->getQtyOrdered();
            $cancelQty += $item->getQtyCanceled();
            $shipQty += $item->getQtyShipped();
            if($item->getQtyShipped() > 0 && $item->getRtoStatus() != 'delivery_not_received') {
                $shippedAmt += $item->getRowTotal();
            }
        }

        if(($cancelQty+$shipQty) == $orderQty) {
            return $shippedAmt;
        } else {
            return 0;
        }
    }
}
