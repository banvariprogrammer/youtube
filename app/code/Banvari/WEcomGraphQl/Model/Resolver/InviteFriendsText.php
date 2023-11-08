<?php

declare(strict_types=1);

namespace Banvari\Referral\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class InviteFriendsText implements \Magento\Framework\GraphQl\Query\ResolverInterface
{
    protected $scopeConfig;
    protected $storeManager;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
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
        $text = '';
        try{
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $storeCode = $this->storeManager->getStore()->getCode();
            $text = $this->scopeConfig->getValue('referral/general/invite_friends', $storeScope, $storeCode);

        } catch(\Exception $e){
            
        }
        return __($text);
    }
}