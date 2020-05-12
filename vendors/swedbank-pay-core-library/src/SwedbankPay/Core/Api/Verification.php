<?php

namespace SwedbankPay\Core\Api;

use SwedbankPay\Core\Data;

/**
 * Class Verification
 * @package SwedbankPay\Core\Api
 * @todo Add more methods
 */
class Verification extends Data implements VerificationInterface
{
    /**
     * Verification constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }

    /**
     * Get Payment Token.
     *
     * @return string
     */
    public function getPaymentToken()
    {
        return $this->getData(self::PAYMENT_TOKEN);
    }

    /**
     * Get Recurrence Token.
     *
     * @return array
     */
    public function getRecurrenceToken()
    {
        return $this->getData(self::RECURRENCE_TOKEN);
    }

    /**
     * Get Masked Pan.
     *
     * @return array
     */
    public function getMaskedPan()
    {
        return $this->getData(self::MASKED_PAN);
    }

    /**
     * Get Card Brand.
     *
     * @return array
     */
    public function getCardBrand()
    {
        return $this->getData(self::CARD_BRAND);
    }

    /**
     * Get Expire Date.
     *
     * @return array
     */
    public function getExpireDate()
    {
        return $this->getData(self::EXPIRY_DATE);
    }
}
