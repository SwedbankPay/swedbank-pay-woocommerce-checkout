<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;
use SwedbankPay\Core\OrderInterface;

trait Invoice
{
    /**
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function initiateInvoicePayment($orderId)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        $params = [
            'payment' => [
                'operation' => self::OPERATION_FINANCING_CONSUMER,
                'intent' => self::INTENT_AUTHORIZATION,
                'currency' => $order->getCurrency(),
                'prices' => [
                    [
                        'type' => 'Invoice',
                        'amount' => $order->getAmountInCents(),
                        'vatAmount' => $order->getVatAmountInCents()
                    ]
                ],
                'description' => $order->getDescription(),
                'payerReference' => $order->getPayerReference(),
                'userAgent' => $order->getHttpUserAgent(),
                'language' => $order->getLanguage(),
                'urls' => [
                    'completeUrl' => $urls->getCompleteUrl(),
                    'cancelUrl' => $urls->getCancelUrl(),
                    'callbackUrl' => $urls->getCallbackUrl(),
                    'termsOfServiceUrl' => $this->configuration->getTermsUrl()
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'riskIndicator' => $this->getRiskIndicator($orderId)->toArray(),
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
            ],
            'invoice' => [
                'invoiceType' => 'PayExFinancing' . ucfirst(strtolower($order->getBillingCountryCode()))
            ]
        ];

        try {
            $result = $this->request('POST', '/psp/invoice/payments', $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

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
    public function getApprovedLegalAddress($legalAddressHref, $socialSecurityNumber, $postCode)
    {
        $params = [
            'addressee' => [
                'socialSecurityNumber' => $socialSecurityNumber,
                'zipCode' => str_replace(' ', '', $postCode)
            ]
        ];

        try {
            $result = $this->request('POST', $legalAddressHref, $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        // @todo Implement LegalAddress class

        return $result;
    }

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
    ) {
        /** @var Order $order */
        $order = $this->getOrder($orderId);


        $params = [
            'transaction' => [
                'activity' => 'FinancingConsumer'
            ],
            'consumer' => [
                'socialSecurityNumber' => $ssn,
                'customerNumber' => $order->getCustomerId(),
                'email' => $order->getBillingEmail(),
                'msisdn' => '+' . ltrim($order->getBillingPhone(), '+'),
                'ip' => $order->getHttpUserAgent()
            ],
            'legalAddress' => [
                'addressee' => $addressee,
                'coAddress' => $coAddress,
                'streetAddress' => $streetAddress,
                'zipCode' => $zipCode,
                'city' => $city,
                'countryCode' => $countryCode
            ]
        ];

        try {
            $result = $this->request('POST', $authorizeHref, $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * Capture Invoice.
     *
     * @param mixed $orderId
     * @param int|float $amount
     * @param int|float $vatAmount
     * @param array $items
     *
     * @return Response
     * @throws Exception
     */
    public function captureInvoice($orderId, $amount = null, $vatAmount = 0, array $items = [])
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        if (!$amount) {
            $amount = $order->getAmount();
            $vatAmount = $order->getVatAmount();
        }

        if (!$this->canCapture($orderId, $amount)) {
            throw new Exception('Capturing is not available.');
        }

        $paymentId = $order->getPaymentId();
        if (empty($paymentId)) {
            throw new Exception('Unable to get payment ID');
        }

        $href = $this->fetchPaymentInfo($paymentId)->getOperationByRel('create-capture');
        if (empty($href)) {
            throw new Exception('Capture is unavailable');
        }

        $params = [
            'transaction' => [
                'activity' => 'FinancingConsumer',
                'amount' => (int)bcmul(100, $amount),
                'vatAmount' => (int)bcmul(100, $vatAmount),
                'description' => sprintf('Capture for Order #%s', $order->getOrderId()),
                'payeeReference' => $this->generatePayeeReference($orderId)
            ],
            'itemDescriptions' => $items
        ];

        $result = $this->request('POST', $href, $params);

        // Save transaction
        $transaction = $result['capture']['transaction'];
        $this->saveTransaction($orderId, $transaction);

        switch ($transaction['state']) {
            case 'Completed':
                $this->updateOrderStatus(OrderInterface::STATUS_CAPTURED, 'Transaction is captured.');
                break;
            case 'Initialized':
                $this->updateOrderStatus(OrderInterface::STATUS_AUTHORIZED,
                    sprintf('Transaction capture status: %s.', $transaction['state']));
                break;
            case 'Failed':
                $message = isset($transaction['failedReason']) ? $transaction['failedReason'] : 'Capture is failed.';
                throw new Exception($message);
            default:
                throw new Exception('Capture is failed.');
        }

        return $result;
    }

    /**
     * Cancel Invoice.
     *
     * @param mixed $orderId
     * @param int|float|null $amount
     * @param int|float $vatAmount
     *
     * @return Response
     * @throws Exception
     */
    public function cancelInvoice($orderId, $amount = null, $vatAmount = 0)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        if (!$amount) {
            $amount = $order->getAmount();
            $vatAmount = $order->getVatAmount();
        }

        if (!$this->canCancel($orderId, $amount)) {
            throw new Exception('Cancellation is not available.');
        }

        $paymentId = $order->getPaymentId();
        if (empty($paymentId)) {
            throw new Exception('Unable to get payment ID');
        }

        $href = $this->fetchPaymentInfo($paymentId)->getOperationByRel('create-cancellation');
        if (empty($href)) {
            throw new Exception('Cancellation is unavailable');
        }

        $params = [
            'transaction' => [
                'activity' => 'FinancingConsumer',
                'description' => sprintf('Cancellation for Order #%s', $order->getOrderId()),
                'payeeReference' => $this->generatePayeeReference($orderId)
            ],
        ];

        $result = $this->request('POST', $href, $params);

        // Save transaction
        $transaction = $result['cancellation']['transaction'];
        $this->saveTransaction($orderId, $transaction);

        switch ($transaction['state']) {
            case 'Completed':
                $this->updateOrderStatus(OrderInterface::STATUS_CANCELLED, 'Transaction is cancelled.');
                break;
            case 'Initialized':
            case 'AwaitingActivity':
                $this->updateOrderStatus(OrderInterface::STATUS_CANCELLED,
                    sprintf('Transaction cancellation status: %s.', $transaction['state']));
                break;
            case 'Failed':
                $message = isset($transaction['failedReason']) ? $transaction['failedReason'] : 'Cancellation is failed.';
                throw new Exception($message);
            default:
                throw new Exception('Capture is failed.');
        }

        return $result;
    }

    /**
     * Refund Invoice.
     *
     * @param mixed $orderId
     * @param int|float|null $amount
     * @param int|float $vatAmount
     *
     * @return Response
     * @throws Exception
     */
    public function refundInvoice($orderId, $amount = null, $vatAmount = 0)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        if (!$amount) {
            $amount = $order->getAmount();
            $vatAmount = $order->getVatAmount();
        }

        if (!$this->canRefund($orderId, $amount)) {
            throw new Exception('Refund action is not available.');
        }

        $paymentId = $order->getPaymentId();
        if (empty($paymentId)) {
            throw new Exception('Unable to get payment ID');
        }

        $href = $this->fetchPaymentInfo($paymentId)->getOperationByRel('create-reversal');
        if (empty($href)) {
            throw new Exception('Refund is unavailable');
        }

        $params = [
            'transaction' => [
                'activity' => 'FinancingConsumer',
                'amount' => (int)bcmul(100, $amount),
                'vatAmount' => (int)bcmul(100, $vatAmount),
                'description' => sprintf('Refund for Order #%s.', $order->getOrderId()),
                'payeeReference' => $this->generatePayeeReference($orderId)
            ]
        ];

        $result = $this->request('POST', $href, $params);

        // Save transaction
        $transaction = $result['reversal']['transaction'];
        $this->saveTransaction($orderId, $transaction);

        switch ($transaction['state']) {
            case 'Completed':
                $this->updateOrderStatus(
                    OrderInterface::STATUS_REFUNDED,
                    sprintf('Refunded: %s.', $amount)
                );
                break;
            case 'Initialized':
            case 'AwaitingActivity':
                $this->updateOrderStatus(OrderInterface::STATUS_CANCELLED,
                    sprintf('Transaction reversal status: %s.', $transaction['state'])
                );
                break;
            case 'Failed':
                $message = isset($transaction['failedReason']) ? $transaction['failedReason'] : 'Refund is failed.';
                throw new Exception($message);
            default:
                throw new Exception('Refund is failed.');
        }

        return $result;
    }
}
