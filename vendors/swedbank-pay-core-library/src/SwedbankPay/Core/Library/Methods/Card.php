<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;

trait Card
{
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
    public function initiateCreditCardPayment($orderId, $generateToken, $paymentToken)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        // Process payment
        $params = [
            'payment' => [
                'operation' => self::OPERATION_PURCHASE,
                'intent' => $this->configuration->getAutoCapture() ? self::INTENT_AUTOCAPTURE : self::INTENT_AUTHORIZATION,
                'currency' => $order->getCurrency(),
                'prices' => [
                    [
                        'type' => self::TYPE_CREDITCARD,
                        'amount' => $order->getAmountInCents(),
                        'vatAmount' => $order->getVatAmountInCents(),
                    ]
                ],
                'description' => $order->getDescription(),
                'payerReference' => $order->getPayerReference(),
                'generatePaymentToken' => $generateToken,
                'generateRecurrenceToken' => $generateToken,
                'pageStripdown' => false,
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
                'cardholder' => $order->getCardHolderInformation(),
                'creditCard' => [
                    'rejectCreditCards' => $this->configuration->getRejectCreditCards(),
                    'rejectDebitCards' => $this->configuration->getRejectDebitCards(),
                    'rejectConsumerCards' => $this->configuration->getRejectConsumerCards(),
                    'rejectCorporateCards' => $this->configuration->getRejectCorporateCards()
                ],
                'prefillInfo' => [
                    'msisdn' => '+' . ltrim($order->getBillingPhone(), '+')
                ],
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
            ]
        ];

        if ($paymentToken) {
            $params['payment']['paymentToken'] = $paymentToken;
            $params['payment']['generatePaymentToken'] = false;
            $params['payment']['generateRecurrenceToken'] = false;
        }

        try {
            $result = $this->request('POST', '/psp/creditcard/payments', $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * Initiate a New Credit Card Payment
     *
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function initiateNewCreditCardPayment($orderId)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        $params = [
            'payment' => [
                'operation' => self::OPERATION_VERIFY,
                'currency' => $order->getCurrency(),
                'description' => 'Verification of Credit Card',
                'payerReference' => $order->getPayerReference(),
                'generatePaymentToken' => true,
                'generateRecurrenceToken' => true,
                'pageStripdown' => false,
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
                'cardholder' => $order->getCardHolderInformation(),
                'creditCard' => [
                    'rejectCreditCards' => $this->configuration->getRejectCreditCards(),
                    'rejectDebitCards' => $this->configuration->getRejectDebitCards(),
                    'rejectConsumerCards' => $this->configuration->getRejectConsumerCards(),
                    'rejectCorporateCards' => $this->configuration->getRejectCorporateCards()
                ],
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
            ]
        ];

        try {
            $result = $this->request('POST', '/psp/creditcard/payments', $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

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
    public function initiateCreditCardRecur($orderId, $recurrenceToken, $paymentToken = null)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $params = [
            'payment' => [
                'operation' => self::OPERATION_RECUR,
                'intent' => $this->configuration->getAutoCapture() ? self::INTENT_AUTOCAPTURE : self::INTENT_AUTHORIZATION,
                'currency' => $order->getCurrency(),
                'amount' => $order->getAmountInCents(),
                'vatAmount' => $order->getVatAmountInCents(),
                'description' => $order->getDescription(),
                'payerReference' => $order->getPayerReference(),
                'userAgent' => $order->getHttpUserAgent(),
                'language' => $order->getLanguage(),
                'urls' => [
                    'callbackUrl' => $this->getPlatformUrls($orderId)->getCallbackUrl()
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'riskIndicator' => $this->getRiskIndicator($orderId)->toArray(),
                'metadata' => [
                    'order_id' => $orderId
                ],
            ]
        ];

        // Use Recurrence Token if it's exist
        if (!empty($recurrenceToken)) {
            $params['payment']['recurrenceToken'] = $recurrenceToken;
        } else {
            $params['payment']['paymentToken'] = $paymentToken;
        }

        try {
            $result = $this->request('POST', '/psp/creditcard/payments', $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw $e;
        }

        return $result;
    }
}
