<?php

namespace SwedbankPay\Core\Api;

use SwedbankPay\Core\Data;

/**
 * Class Transaction
 * @package SwedbankPay\Core\Api
 * @method string getId()
 * @method $this setId($value)
 * @method string getCreated()
 * @method $this setCreated($value)
 * @method string getUpdated()
 * @method $this setUpdated($value)
 * @method string getType()
 * @method $this setType($value)
 * @method string getState()
 * @method $this setState($value)
 * @method int getNumber()
 * @method $this setNumber($value)
 * @method int getAmount()
 * @method $this setAmount($value)
 * @method $this setVatAmount($value)
 * @method string getDescription()
 * @method $this setDescription($value)
 * @method $this setPayeeReference($value)
 */
class Transaction extends Data implements TransactionInterface
{
    /**
     * Transaction constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }

    /**
     * Get VAT amount.
     *
     * @return string
     */
    public function getVatAmount()
    {
        return $this->getData(self::VAT_AMOUNT);
    }

    /**
     * Get Payee Reference.
     *
     * @return string
     */
    public function getPayeeReference()
    {
        return $this->getData(self::PAYEE_REFERENCE);
    }

    /**
     * Get Failed Reason.
     *
     * @return string
     */
    public function getFailedReason()
    {
        return $this->getData(self::FAILED_REASON);
    }

    /**
     * Get Failed Error Code.
     *
     * @return string
     */
    public function getFailedErrorCode()
    {
        return $this->getData(self::FAILED_ERROR_CODE);
    }

    /**
     * Get Failed Error Description.
     *
     * @return string
     */
    public function getFailedErrorDescription()
    {
        return $this->getData(self::FAILED_ERROR_DESCRIPTION);
    }

    /**
     * Get Failed Details.
     *
     * @return string
     */
    public function getFailedDetails()
    {
        return implode('; ', [
            $this->getFailedReason(),
            $this->getFailedErrorCode(),
            $this->getFailedErrorDescription()
        ]);
    }

    /**
     * Is Pending.
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->getState() === self::STATE_PENDING;
    }

    /**
     * Is Completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return $this->getState() === self::STATE_COMPLETED;
    }

    /**
     * Is Failed.
     *
     * @return bool
     */
    public function isFailed()
    {
        return $this->getState() === self::STATE_FAILED;
    }
}
