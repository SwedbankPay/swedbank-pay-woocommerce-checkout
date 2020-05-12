<?php

namespace SwedbankPay\Core\Api;

interface VerificationInterface
{
    const PAYMENT_TOKEN = 'paymentToken';
    const RECURRENCE_TOKEN = 'recurrenceToken';
    const CARD_BRAND = 'cardBrand';
    const MASKED_PAN = 'maskedPan';
    const EXPIRY_DATE = 'expiryDate';

    // @todo Add more consts

    /**
     * Get Payment Token.
     *
     * @return string
     */
    public function getPaymentToken();

    /**
     * Get Recurrence Token.
     *
     * @return array
     */
    public function getRecurrenceToken();

    /**
     * Get Masked Pan.
     *
     * @return array
     */
    public function getMaskedPan();

    /**
     * Get Card Brand.
     *
     * @return array
     */
    public function getCardBrand();

    /**
     * Get Expire Date.
     *
     * @return array
     */
    public function getExpireDate();
}
