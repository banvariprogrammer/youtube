<?php
namespace Banvari\WhatsappEcommerce\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    protected $resource;
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
        ){
            $this->resource = $resource;
            $this->scopeConfig = $scopeConfig;
        }

    public function getCurlResult($apiUrl, $body, $method, $token='') {
        try{
            if($token != '') {
                $header = array('Content-Type: application/json', 'Authorization: Bearer '.$token);
            } else {
                $header = array('Content-Type: application/json');
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => $header
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        } catch(\Exception $e){
            throw $e->getMessage();
        }
    } 

    public function getProductSize($sku) {
        $connection = $this->resource->getConnection();
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $sizeId = $this->scopeConfig->getValue('wa_commerce/general/size_id', $storeScope);
        $sizeLabel = $this->scopeConfig->getValue('wa_commerce/general/size_label', $storeScope);

        $skus = implode("','", explode(",", $sku));
        $sql = "SELECT 
                    cpe.sku, 
                    eaov.value AS option_value,
                    '".$sizeLabel."' AS option_label
                FROM 
                    catalog_product_entity AS cpe
                    JOIN catalog_product_entity_int AS cpei ON cpei.row_id = cpe.row_id 
                    JOIN eav_attribute_option_value AS eaov ON eaov.option_id = cpei.value 
                WHERE
                    cpei.attribute_id = :size_id
                    AND eaov.store_id = 0
                    AND cpe.sku in ('".$skus."')";

        $sqlBinds = array('size_id' => $sizeId);
        $simpleSkus = $connection->fetchAll($sql, $sqlBinds);
        return $simpleSkus;
    }

    public function styleItWithWidget($custId) {
        $connection = $this->resource->getConnection();
        $sql = "SELECT 
                    config_sku 
                FROM 
                    sales_order_item 
                WHERE 
                    order_id = (
                        SELECT 
                            so.entity_id 
                        FROM 
                            sales_order AS so 
                        WHERE 
                            customer_id = :customer_id 
                        ORDER BY 
                            entity_id DESC 
                        LIMIT 
                            1
                    )";
        $sqlBinds = array('customer_id' => $custId);
        $allSkus = $connection->fetchAll($sql, $sqlBinds);
        $totalItems = count($allSkus);
        if($totalItems > 0) {
            return $allSkus;
        } else {
            return 'recently_viewed_widget';
        }
    }
}