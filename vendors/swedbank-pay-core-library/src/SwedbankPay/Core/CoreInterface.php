<?php

namespace SwedbankPay\Core;

use SwedbankPay\Core\Api\Authorization;
use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Api\Transaction;
use SwedbankPay\Core\Api\Verification;

interface CoreInterface
{
    const INTENT_AUTOCAPTURE = 'AutoCapture';
    const INTENT_AUTHORIZATION = 'Authorization';
    const INTENT_SALE = 'Sale';

    const OPERATION_PURCHASE = 'Purchase';
    const OPERATION_VERIFY = 'Verify';
    const OPERATION_RECUR = 'Recur';
    const OPERATION_FINANCING_CONSUMER = 'FinancingConsumer';
    const TYPE_CREDITCARD = 'CreditCard';

    /**
     * Initiate a Credit Card Payment
     *
     * @param mixed $orderId
     * @param bool $generateToken
     * @param string $paymentToken
     *
     * @return Response
     * @throws Exception
     */
    public function initiateCreditCardPayment($orderId, $generateToken, $paymentToken);

    /**
     * Initiate a New Credit Card Payment
     *
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function initiateNewCreditCardPayment($orderId);

    /**
     * Initiate a CreditCard Recurrent Payment
     *
     * @param mixed $orderId
     * @param string $recurrenceToken
     * @param string|null $paymentToken
     *
     * @return Response
     * @throws \Exception
     */
    public function initiateCreditCardRecur($orderId, $recurrenceToken, $paymentToken = null);

    /**
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function initiateInvoicePayment($orderId);

    /**
     * Get Approved Legal Address.
     *
     * @param string $legalAddressHref
     * @param string $socialSecurityNumber
     * @param string $postCode
     *
     * @return Response
     * @throws Exception
     */
    public function getApprovedLegalAddress($legalAddressHref, $socialSecurityNumber, $postCode);

    /**
     * Initiate a Financing Consumer Transaction
     *
     * @param string $authorizeHref
     * @param string $orderId
     * @param string $ssn
     * @param string $addressee
     * @param string $coAddress
     * @param string $streetAddress
     * @param string $zipCode
     * @param string $city
     * @param string $countryCode
     *
     * @return Response
     * @throws Exception
     */
    public function transactionFinancingConsumer(
        $authorizeHref,
        $orderId,
        $ssn,
        $addressee,
        $coAddress,
        $streetAddress,
        $zipCode,
        $city,
        $countryCode
    );

    /**
     * Capture Invoice.
     *
     * @param mixed $orderId
     * @param int|float $amount
     * @param array $items
     *
     * @return Response
     * @throws Exception
     */
    public function captureInvoice($orderId, $amount = null, array $items = []);

    /**
     * Cancel Invoice.
     *
     * @param mixed $orderId
     * @param int|float|null $amount
     *
     * @return Response
     * @throws Exception
     */
    public function cancelInvoice($orderId, $amount = null);

    /**
     * Refund Invoice.
     *
     * @param mixed $orderId
     * @param int|float|null $amount
     * @param string|null reason
     *
     * @return Response
     * @throws Exception
     */
    public function refundInvoice($orderId, $amount = null, $reason = null);

    public function canCapture($orderId, $amount = null);

    public function canCancel($orderId, $amount = null);

    public function canRefund($orderId, $amount = null);

    public function capture($orderId, $amount = null);

    public function cancel($orderId, $amount = null);

    public function refund($orderId, $amount = null, $reason = null);

    public function abort($orderId);

    public function updateOrderStatus($orderId, $status, $message = null, $transactionId = null);

    /**
     * @param $orderId
     * @param Api\Transaction|array $transaction
     *
     * @throws Exception
     */
    public function processTransaction($orderId, $transaction);

    /**
     * @param $orderId
     *
     * @return string
     */
    public function generatePayeeReference($orderId);

    /**
     * Do API Request
     *
     * @param       $method
     * @param       $url
     * @param array $params
     *
     * @return Response
     * @throws \Exception
     */
    public function request($method, $url, $params = []);

    /**
     * Fetch Payment Info.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Response
     * @throws Exception
     */
    public function fetchPaymentInfo($paymentIdUrl, $expand = null);

    /**
     * Fetch Transaction List.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Transaction[]
     * @throws Exception
     */
    public function fetchTransactionsList($paymentIdUrl, $expand = null);

    /**
     * Fetch Verification List.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Verification[]
     * @throws Exception
     */
    public function fetchVerificationList($paymentIdUrl, $expand = null);

    /**
     * Fetch Authorization List.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Authorization[]
     * @throws Exception
     */
    public function fetchAuthorizationList($paymentIdUrl, $expand = null);

    /**
     * Initiate Swish Payment
     *
     * @param mixed $orderId
     * @param string $phone
     * @param bool $ecomOnlyEnabled
     *
     * @return Response
     * @throws Exception
     */
    public function initiateSwishPayment($orderId, $phone, $ecomOnlyEnabled = true);

    /**
     * initiate Swish Payment Direct
     *
     * @param string $saleHref
     * @param string $phone
     *
     * @return mixed
     * @throws Exception
     */
    public function initiateSwishPaymentDirect($saleHref, $phone);

    /**
     * Save Transaction Data.
     *
     * @param mixed $orderId
     * @param array $transactionData
     */
    public function saveTransaction($orderId, $transactionData = []);

    /**
     * Save Transactions Data.
     *
     * @param mixed $orderId
     * @param array $transactions
     */
    public function saveTransactions($orderId, array $transactions);

    /**
     * Find Transaction.
     *
     * @param string $field
     * @param mixed $value
     *
     * @return bool|Transaction
     */
    public function findTransaction($field, $value);

    /**
     * Initiate Vipps Payment.
     *
     * @param mixed $orderId
     * @param string $phone
     *
     * @return mixed
     * @throws Exception
     */
    public function initiateVippsPayment($orderId, $phone);
}