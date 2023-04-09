<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Banvari\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
/**
 * Customer is_subscribed field resolver
 */
class TestUser implements ResolverInterface
{
    private $_customerFactory;
    protected $orderCollectionFactory;

    public function __construct(
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
        ){
            $this->_customerFactory = $customerFactory;
            $this->orderCollectionFactory = $orderCollectionFactory;
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
        if(!isset($args['email']) || empty($args['email'])){
            throw new GraphQlAuthorizationException(__('Email for customer should be specified',[\Magento\Customer\Model\Customer::ENTITY]));
        }
        try{
            return $this->getCustomerData($args['email']);
        }catch(NoSuchEntityException $e){
            throw new GraphQlNoSuchEntityException(__($e->getMessage()));
        }
        return $output;
    }

    private function getCustomerData($email):array{
        try{
            $customerData = [];
            $customerColl = $this->_customerFactory->create()->getCollection()->addFieldToFilter("email",array("eq"=>$email));
            $customerData = $customerColl->getData();
            if(isset($customerData[0])){
                $customerId = $customerData[0]['entity_id'];

                $orders = $this->orderCollectionFactory->create()->addFieldToFilter(
                    'customer_id',
                    $customerId
                );

                if(count($orders) > 0) {
                    foreach($orders as $key => $order) {
                        $customerData[0]['allOrders'][$key]['order_number'] = $order->getIncrementId();
                        $customerData[0]['allOrders'][$key]['created_at'] = $order->getCreatedAt();
                        $customerData[0]['allOrders'][$key]['status'] = $order->getStatus();
                    }
                }

                return $customerData[0];
            }else{
                return [];
            }
            
        }catch(LocalizedException $e){
            throw new NoSuchEntityException(__($e->getMessage()));
        }
    }
}