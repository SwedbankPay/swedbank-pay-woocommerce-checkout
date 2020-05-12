<?php

use SwedbankPay\Core\PaymentAdapter;
use SwedbankPay\Core\PaymentAdapterInterface;
use SwedbankPay\Core\ConfigurationInterface;
use SwedbankPay\Core\Order\PlatformUrlsInterface;
use SwedbankPay\Core\OrderInterface;
use SwedbankPay\Core\OrderItemInterface;
use SwedbankPay\Core\Order\RiskIndicatorInterface;
use SwedbankPay\Core\Order\PayeeInfoInterface;

class Adapter extends PaymentAdapter implements PaymentAdapterInterface
{
    /**
     * @var Gateway
     */
    private $gateway;

    /**
     * Adapter constructor.
     *
     * @param Gateway $gateway
     */
    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Log a message.
     *
     * @param $level
     * @param $message
     * @param array $context
     */
    public function log($level, $message, array $context = [])
    {
        // @todo
    }

    /**
     * Get Adapter Configuration.
     *
     * @return array
     */
    public function getConfiguration()
    {
        return [
            ConfigurationInterface::DEBUG => $this->gateway->debug,
            ConfigurationInterface::MERCHANT_TOKEN => $this->gateway->merchant_token,
            ConfigurationInterface::PAYEE_ID => $this->gateway->payee_id,
            ConfigurationInterface::PAYEE_NAME => $this->gateway->payee_name,
            ConfigurationInterface::MODE => $this->gateway->testmode,
            ConfigurationInterface::AUTO_CAPTURE => $this->gateway->auto_capture,
            ConfigurationInterface::SUBSITE => $this->gateway->subsite,
            ConfigurationInterface::LANGUAGE => $this->gateway->language,
            ConfigurationInterface::SAVE_CC => $this->gateway->save_cc,
            ConfigurationInterface::TERMS_URL => $this->gateway->terms_url,
            ConfigurationInterface::REJECT_CREDIT_CARDS => $this->gateway->reject_credit_cards,
            ConfigurationInterface::REJECT_DEBIT_CARDS => $this->gateway->reject_debit_cards,
            ConfigurationInterface::REJECT_CONSUMER_CARDS => $this->gateway->reject_consumer_cards,
            ConfigurationInterface::REJECT_CORPORATE_CARDS => $this->gateway->reject_corporate_cards,
        ];
    }

    /**
     * Get Platform Urls of Actions of Order (complete, cancel, callback urls).
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getPlatformUrls($orderId)
    {
        return [
            PlatformUrlsInterface::COMPLETE_URL => 'https://example.com/complete',
            PlatformUrlsInterface::CANCEL_URL => 'https://example.com/cancel',
            PlatformUrlsInterface::CALLBACK_URL => 'https://example.com/callback',
            PlatformUrlsInterface::TERMS_URL => 'https://example.com/terms'
        ];
    }

    /**
     * Get Order Data.
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getOrderData($orderId)
    {
        return [
            OrderInterface::ORDER_ID => null,
            OrderInterface::AMOUNT => null,
            OrderInterface::VAT_AMOUNT => null,
            OrderInterface::VAT_RATE => null,
            OrderInterface::SHIPPING_AMOUNT => null,
            OrderInterface::SHIPPING_VAT_AMOUNT => null,
            OrderInterface::DESCRIPTION => null,
            OrderInterface::CURRENCY => null,
            OrderInterface::STATUS => null,
            OrderInterface::CREATED_AT => null,
            OrderInterface::PAYMENT_ID => null,
            OrderInterface::PAYMENT_ORDER_ID => null,
            OrderInterface::NEEDS_SAVE_TOKEN_FLAG => null,
            OrderInterface::HTTP_ACCEPT => null,
            OrderInterface::HTTP_USER_AGENT => null,
            OrderInterface::BILLING_COUNTRY => null,
            OrderInterface::BILLING_COUNTRY_CODE => null,
            OrderInterface::BILLING_ADDRESS1 => null,
            OrderInterface::BILLING_ADDRESS2 => null,
            OrderInterface::BILLING_ADDRESS3 => null,
            OrderInterface::BILLING_CITY => null,
            OrderInterface::BILLING_STATE => null,
            OrderInterface::BILLING_POSTCODE => null,
            OrderInterface::BILLING_PHONE => null,
            OrderInterface::BILLING_EMAIL => null,
            OrderInterface::BILLING_FIRST_NAME => null,
            OrderInterface::BILLING_LAST_NAME => null,
            OrderInterface::SHIPPING_COUNTRY => null,
            OrderInterface::SHIPPING_COUNTRY_CODE => null,
            OrderInterface::SHIPPING_ADDRESS1 => null,
            OrderInterface::SHIPPING_ADDRESS2 => null,
            OrderInterface::SHIPPING_ADDRESS3 => null,
            OrderInterface::SHIPPING_CITY => null,
            OrderInterface::SHIPPING_STATE => null,
            OrderInterface::SHIPPING_POSTCODE => null,
            OrderInterface::SHIPPING_PHONE => null,
            OrderInterface::SHIPPING_EMAIL => null,
            OrderInterface::SHIPPING_FIRST_NAME => null,
            OrderInterface::SHIPPING_LAST_NAME => null,
            OrderInterface::CUSTOMER_ID => null,
            OrderInterface::CUSTOMER_IP => null,
            OrderInterface::PAYER_REFERENCE => null,
            OrderInterface::ITEMS => null,
            OrderInterface::LANGUAGE => null,
        ];
    }

    /**
     * Get Risk Indicator of Order.
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getRiskIndicator($orderId)
    {
        // @todo
    }

    /**
     * Get Payee Info of Order.
     *
     * @param mixed $orderId
     *
     * @return array
     */
    public function getPayeeInfo($orderId)
    {
        // @todo
    }

    /**
     * Update Order Status.
     *
     * @param mixed $orderId
     * @param string $status
     * @param string|null $message
     * @param mixed|null $transactionId
     */
    public function updateOrderStatus($orderId, $status, $message = null, $transactionId = null)
    {
        // @todo
    }

    /**
     * Save Transaction data.
     *
     * @param mixed $orderId
     * @param array $transactionData
     */
    public function saveTransaction($orderId, array $transactionData = [])
    {
        // @todo
    }

    /**
     * Find for Transaction.
     *
     * @param $field
     * @param $value
     *
     * @return array
     */
    public function findTransaction($field, $value)
    {
        // @todo
    }

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
    ) {
        // @todo
    }
}
