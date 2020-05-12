<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;

trait Checkout
{
    /**
     * Initiate Payment Order Purchase.
     *
     * @param mixed $orderId
     * @param string|null $consumerProfileRef
     *
     * @return Response
     * @throws Exception
     */
    public function initiatePaymentOrderPurchase($orderId, $consumerProfileRef = null)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        $params = [
            'paymentorder' => [
                'operation' => self::OPERATION_PURCHASE,
                'currency' => $order->getCurrency(),
                'amount' => $order->getAmountInCents(),
                'vatAmount' => $order->getVatAmountInCents(),
                'description' => $order->getDescription(),
                'userAgent' => $order->getHttpUserAgent(),
                'language' => $order->getLanguage(),
                'generateRecurrenceToken' => false,
                'disablePaymentMenu' => false,
                'urls' => [
                    'hostUrls' => $urls->getHostUrls(),
                    'completeUrl' => $urls->getCompleteUrl(),
                    'cancelUrl' => $urls->getCancelUrl(),
                    'callbackUrl' => $urls->getCallbackUrl(),
                    'termsOfServiceUrl' => $this->configuration->getTermsUrl()
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'payer' => $order->getCardHolderInformation(),
                'orderItems' => $order->getItems(),
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
                'items' => [
                    [
                        'creditCard' => [
                            'rejectCreditCards' => $this->configuration->getRejectCreditCards(),
                            'rejectDebitCards' => $this->configuration->getRejectDebitCards(),
                            'rejectConsumerCards' => $this->configuration->getRejectConsumerCards(),
                            'rejectCorporateCards' => $this->configuration->getRejectCorporateCards()
                        ]
                    ]
                ]
            ]
        ];

        // Add consumerProfileRef if exists
        if (!empty($consumerProfileRef)) {
            $params['paymentorder']['payer'] = [
                'consumerProfileRef' => $consumerProfileRef
            ];
        }

        try {
            $result = $this->request('POST', '/psp/paymentorders', $params);
        } catch (\SwedbankPay\Core\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage(), $e->getCode(), null, $e->getProblems());
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * @param string $updateUrl
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function updatePaymentOrder($updateUrl, $orderId)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        // Update Order
        $params = [
            'paymentorder' => [
                'operation' => 'UpdateOrder',
                'amount' => $order->getAmountInCents(),
                'vatAmount' => $order->getVatAmountInCents(),
                'orderItems' => $order->getItems()
            ]
        ];

        try {
            $result = $this->request('PATCH', $updateUrl, $params);
        } catch (\SwedbankPay\Core\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage(), $e->getCode(), null, $e->getProblems());
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * Get Payment ID url by Payment Order.
     *
     * @param string $paymentOrderId
     *
     * @return string|false
     */
    public function getPaymentIdByPaymentOrder($paymentOrderId)
    {
        $payments = $this->request('GET', $paymentOrderId . '/payments');
        foreach ($payments['payments']['paymentList'] as $payment) {
            // Use the first item
            return $payment['id'];
        }

        return false;
    }

    /**
     * Initiate consumer session.
     *
     * @param string $countryCode
     *
     * @return Response
     * @throws Exception
     */
    public function initiateConsumerSession($countryCode)
    {
        $params = [
            'operation' => 'initiate-consumer-session',
            'consumerCountryCode' => $countryCode,
        ];

        try {
            $result = $this->request('POST', '/psp/consumers', $params);
        } catch (\SwedbankPay\Core\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage(), $e->getCode(), null, $e->getProblems());
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }
}
