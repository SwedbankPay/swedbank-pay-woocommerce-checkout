<?php

namespace SwedbankPay\Core;

/**
 * Interface PaymentAdapterInterface
 * @package SwedbankPay\Core
 */
interface PaymentAdapterInterface
{
    /**
     * Log a message.
     *
     * @param $level
     * @param $message
     * @param array $context
     *
     * @see WC_Log_Levels
     */
    public function log($level, $message, array $context = []);

    /**
     * Get Adapter Configuration.
     *
     * @return array
     */
    public function getConfiguration();

    /**
     * Get Platform Urls of Actions of Order (complete, cancel, callback urls).
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getPlatformUrls($orderId);

    /**
     * Get Order Data.
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getOrderData($orderId);

    /**
     * Get Risk Indicator of Order.
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getRiskIndicator($orderId);

    /**
     * Get Payee Info of Order.
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getPayeeInfo($orderId);

    /**
     * Update Order Status.
     *
     * @param mixed $orderId
     * @param string $status
     * @param string|null $message
     * @param mixed|null $transactionId
     */
    public function updateOrderStatus($orderId, $status, $message = null, $transactionId = null);

    /**
     * Save Transaction data.
     *
     * @param mixed $orderId
     * @param array $transactionData
     */
    public function saveTransaction($orderId, array $transactionData = []);

    /**
     * Find for Transaction.
     *
     * @param $field
     * @param $value
     *
     * @return array
     */
    public function findTransaction($field, $value);

    /**
     * Save Payment Token.
     *
     * @param mixed $customerId
     * @param string $paymentToken
     * @param string $recurrenceToken
     * @param string $cardBrand
     * @param string $maskedPan
     * @param string $expiryDate
     * @param mixed|null $orderId
     */
    public function savePaymentToken(
        $customerId,
        $paymentToken,
        $recurrenceToken,
        $cardBrand,
        $maskedPan,
        $expiryDate,
        $orderId = null
    );
}
