<?php

namespace SwedbankPay\Core\Api;

/**
 * Interface TransactionInterface
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
interface TransactionInterface
{
    const TYPE_AUTHORIZATION = 'Authorization';
    const TYPE_CAPTURE = 'Capture';
    const TYPE_SALE = 'Sale';
    const TYPE_CANCELLATION = 'Cancellation';
    const TYPE_REVERSAL = 'Reversal';

    const STATE_PENDING = 'Pending';
    const STATE_COMPLETED = 'Completed';
    const STATE_FAILED = 'Failed';

    const VAT_AMOUNT = 'vatAmount';
    const PAYEE_REFERENCE = 'payeeReference';
    const FAILED_REASON = 'failedReason';
    const FAILED_ERROR_CODE = 'failedErrorCode';
    const FAILED_ERROR_DESCRIPTION = 'failedErrorDescription';

    /**
     * Get VAT amount.
     *
     * @return string
     */
    public function getVatAmount();

    /**
     * Get Payee Reference.
     *
     * @return string
     */
    public function getPayeeReference();

    /**
     * Get Failed Reason.
     *
     * @return string
     */
    public function getFailedReason();

    /**
     * Get Failed Error Code.
     *
     * @return string
     */
    public function getFailedErrorCode();

    /**
     * Get Failed Error Description.
     *
     * @return string
     */
    public function getFailedErrorDescription();

    /**
     * Get Failed Details.
     *
     * @return string
     */
    public function getFailedDetails();

    /**
     * Is Pending.
     *
     * @return bool
     */
    public function isPending();

    /**
     * Is Completed.
     *
     * @return bool
     */
    public function isCompleted();

    /**
     * Is Failed.
     *
     * @return bool
     */
    public function isFailed();
}