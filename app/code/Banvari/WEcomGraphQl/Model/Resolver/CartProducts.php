<?php

namespace Banvari\WhatsappEcommerce\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CartProducts implements ResolverInterface
{
    protected $logger;
    protected $helper;
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Banvari\WhatsappEcommerce\Helper\Data $helper
        ){
            $this->scopeConfig = $scopeConfig;
            $this->helper = $helper;
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/whatsapp_ecommerce_cart.log');
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
        if(!isset($args['cart_items']) || empty($args['cart_items'])){
            throw new GraphQlAuthorizationException(__('WhatsApp added to cart data is required'));
        }
        try{
            return $this->addToCartProducts($args);
        } catch(NoSuchEntityException $e){
            throw new GraphQlNoSuchEntityException(__($e->getMessage()));
        }
        return $output;
    }

    private function addToCartProducts($args):array{
        try{
            $result = [];
            $isCart = false;
            $region = $args['region'];

            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $addToCart = $this->scopeConfig->getValue('wa_commerce/general/addtocart_api', $storeScope);
            $cartApi = strstr($addToCart, "{{token}}",true);
            $token = $this->helper->getCurlResult($cartApi, '', 'POST');

            $guestToken = json_decode($token, true);

            $cartApi = str_replace("en-ae", $region, $addToCart);
            $cartApi = str_replace("{{token}}", $guestToken, $cartApi);

            foreach($args['cart_items'] as $items) {
                $body = array();
                $apiUrl = $cartApi . '?csku=' . $items['config_sku'];
                $this->logger->info("add to cart API URL ". $apiUrl);

                $body['quote_id'] = $guestToken;
                $body['sku'] = $items['data'][0]['sku'];
                $body['qty'] = $items['data'][0]['qty'];
                $body['product_option']['extension_attributes']['custom_options'][0]['option_id'] = $items['data'][0]['option_id'];
                $body['product_option']['extension_attributes']['custom_options'][0]['option_value'] = $items['data'][0]['option_value'];

                $bodyData['cartItem'] = $body;
                $this->logger->info("body Data ". json_encode($bodyData));
                
                $this->helper->getCurlResult($apiUrl, $bodyData, 'POST', $guestToken);
                $isCart = true;
            }

            if($isCart) {
                $cartApi = $this->scopeConfig->getValue('wa_commerce/general/cart_api', $storeScope);
                $manageCartApi = str_replace("en-ae", $region, $cartApi);
                $result['response'] = $manageCartApi.'?locale='.$region.'&whatsapp_cart='.$guestToken;
            } else {
                $result['response'] = __('Items are not available in cart request');
            }
            return $result;
            
        } catch(NoSuchEntityException $e){
            throw new NoSuchEntityException(__($e->getMessage()));
        }
    } 
}