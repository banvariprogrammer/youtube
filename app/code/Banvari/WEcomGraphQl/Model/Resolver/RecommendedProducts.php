<?php

declare(strict_types=1);

namespace Banvari\WhatsappEcommerce\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class RecommendedProducts implements ResolverInterface
{
    const WA_ENABLE = 'wa_commerce/general/enable';
    const WA_URL = 'wa_commerce/general/api_url';
    const WA_KEY = 'wa_commerce/general/api_key';
    const WA_NUM = 'wa_commerce/general/num_result';
    const WA_LIKE = 'wa_commerce/general/like_widget';
    const WA_RECENT = 'wa_commerce/general/recently_widget';
    const WA_TOP = 'wa_commerce/general/top_widget';
    const WA_STYLE = 'wa_commerce/general/style_widget';

    protected $resource;
    protected $scopeConfig;
    protected $logger;
    protected $helper;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Banvari\WhatsappEcommerce\Helper\Data $helper
        ){
            $this->resource = $resource;
            $this->scopeConfig = $scopeConfig;
            $this->helper = $helper;
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/whatsapp_ecommerce.log');
            $this->logger = new \Zend\Log\Logger();
            $this->logger->addWriter($writer);
        }
    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        //echo json_encode($args);die;
        if(!isset($args['mobile']) || empty($args['mobile'])){
            throw new GraphQlAuthorizationException(__('Customer mobile number is required in request'));
        }
        try{
            return $this->getRecommendedProducts($args);
        } catch(NoSuchEntityException $e){
            throw new GraphQlNoSuchEntityException(__($e->getMessage()));
        }
        return $output;
    }

    private function getRecommendedProducts($args):array{

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $isEnable = $this->scopeConfig->getValue(self::WA_ENABLE, $storeScope);
        if($isEnable) {
            try{
                $result = [];
                $connection = $this->resource->getConnection();
                $sql = "SELECT entity_id FROM customer_entity WHERE contact_no =:mobile LIMIT 1";

                $sqlBinds = array('mobile' => '+'.$args['mobile']);
                $userId = $connection->fetchOne($sql, $sqlBinds);

                if($userId){
                    $apiUrl = $this->scopeConfig->getValue(self::WA_URL, $storeScope);
                    $apiKey = $this->scopeConfig->getValue(self::WA_KEY, $storeScope);
                    $numResult = $this->scopeConfig->getValue(self::WA_NUM, $storeScope);
                    
                    $body['api_key'] = $apiKey;
                    $body['num_results'] = [(int) $numResult];
                    $body['mad_uuid'] = $userId;
                    $body['details'] = true;
                    $body['user_id'] = '';//$userId;
                    $body['region'] = $args['region'];
                    $body['fields'] = ["product_id", "simple_products"];

                    if($args['widget'] == 'style_it_with' || $args['widget'] == 'you_may_like') {

                        if($args['widget'] == 'style_it_with') {
                            $widget = $this->scopeConfig->getValue(self::WA_STYLE, $storeScope);
                        } else {
                            $widget = $this->scopeConfig->getValue(self::WA_LIKE, $storeScope);
                        }

                        $lastOrderSkus = $this->helper->styleItWithWidget($userId);
                        if(is_array($lastOrderSkus)) {
                            $body['widget_list'] = [(int) $widget];

                            foreach($lastOrderSkus as $inputSku) {
                                $body['product_id'] = $inputSku['config_sku'];
                                
                                $vueRes = $this->helper->getCurlResult($apiUrl, $body, 'POST');
                                $products = json_decode($vueRes, true);

                                if(count($products['data']) > 0)
                                {
                                    $this->logger->info("products from widget ". $widget);
                                    $result['products'] = $this->getRecommendProducts($products);
                                    break;
                                }
                            }
                        } else {
                            $widget = $this->scopeConfig->getValue(self::WA_RECENT, $storeScope);
                            $body['widget_list'] = [(int) $widget];
                        }
                    } else {
                        $widget = $this->scopeConfig->getValue(self::WA_RECENT, $storeScope);
                        $body['widget_list'] = [(int) $widget];
                    }

                    $vueProducts = $this->helper->getCurlResult($apiUrl, $body, 'POST');
                    $products = json_decode($vueProducts, true);
                    
                    if(count($products['data']) > 0){
                        $this->logger->info("products are from widget ". $widget);
                        $result['products'] = $this->getRecommendProducts($products);
                    } else {
                        $widget = $this->scopeConfig->getValue(self::WA_TOP, $storeScope);
                        $body['widget_list'] = [(int) $widget];

                        $this->logger->info("products are from widget ". $widget);

                        $vueProducts = $this->helper->getCurlResult($apiUrl, $body, $method='POST');
                        $products = json_decode($vueProducts, true);

                        if(count($products['data']) > 0){
                            $result['products'] = $this->getRecommendProducts($products);
                        }
                    }
                    $this->logger->info("VUE request payload ". json_encode($body));
                    
                    $result['locale'] = $args['region'];
                }
                return $result;
                
            } catch(NoSuchEntityException $e){
                throw new NoSuchEntityException(__($e->getMessage()));
            }
        } else {
            throw new GraphQlAuthorizationException(__('Please Enable WhatsApp Ecommerce from configuration'));
        }
    } 

    public function getRecommendProducts($products) {
        $i = 0;
        $result = [];

        foreach($products['data'] as $product) {
            foreach($product as $data) {
                $simpleSkus = $this->helper->getProductSize($data['simple_products']);
                $result[$i]['config_sku'] = $data['product_id'];
                $result[$i]['simple_skus'] = $simpleSkus;
                $i++;
            }
        }
        return $result;
    }
}