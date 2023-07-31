<?php
/**
 * Added By Banvari Lal
 * Merge whatsapp Ecommerce Cart with Guest Cart
 */
namespace Banvari\WhatsappEcommerce\Model;

use Banvari\WhatsappEcommerce\Api\CartsMergeInterface;
use Magento\Framework\Exception\LocalizedException;

class CartsMerge implements CartsMergeInterface
{
    const WA_ENABLE = 'wa_commerce/general/enable';
    protected $storeConfig;
    protected $quoteIdMaskFactory;
    protected $resource;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $storeConfig,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->storeConfig = $storeConfig;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->resource = $resource;
    }

    /**
     * @inheritdoc
     */
    public function guestCartMerge($guestCartId, $whatsappCartId)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $isEnable = $this->storeConfig->getValue(self::WA_ENABLE, $storeScope);
        if (!$isEnable) {
            throw new LocalizedException(__('Please Enable WhatsApp Ecommerce from configuration'));
        }

        $response['data'] = $guestCartId;
        header('Content-Type: application/json');

        if($guestCartId == $whatsappCartId) {
            $response['status'] = true;
            echo json_encode($response); exit;
        }
        
        try {
            $guestQuoteIdMask = $this->quoteIdMaskFactory->create()->load($guestCartId, 'masked_id');
            $whatsappQuoteIdMask = $this->quoteIdMaskFactory->create()->load($whatsappCartId, 'masked_id');

            if($whatsappQuoteIdMask->getId()) {

                $connection  = $this->resource->getConnection();
                $quoteId = $guestQuoteIdMask->getQuoteId();
                $whatsappQuoteId = $whatsappQuoteIdMask->getQuoteId();

                $data = ["quote_id"=>$quoteId];
                $where = ['quote_id = ?' => (int)$whatsappQuoteId];

                $tableName = $connection->getTableName("quote_item");
                $connection->update($tableName, $data, $where);

                $response['status'] = true;
                $whatsappQuoteIdMask->delete();
            } else {
                $response['status'] = false;
            }

        } catch (\Exception $e) {
            http_response_code(400);
            $response['status'] = false;
            $response['message'] = __($e->getMessage());
            $response['error'] = __('Something went wrong. Please try again');
        }

        echo json_encode($response);
        exit;
    }
}