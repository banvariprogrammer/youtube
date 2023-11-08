<?php
/**
 * Added By Banvari Lal
 */
namespace Banvari\WhatsappEcommerce\Api;

interface CartsMergeInterface
{
    /**
     * Merge whatsapp Cart to Guest Cart
     *
     * @api
     * @param string $guestCartId
     * @param string $whatsappCartId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function guestCartMerge($guestCartId, $whatsappCartId);
}
