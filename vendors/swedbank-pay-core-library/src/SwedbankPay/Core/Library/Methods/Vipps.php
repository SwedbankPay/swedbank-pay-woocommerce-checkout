<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;

trait Vipps
{
    /**
     * Initiate Vipps Payment.
     *
     * @param mixed $orderId
     * @param string $phone
     *
     * @return mixed
     * @throws Exception
     */
    public function initiateVippsPayment($orderId, $phone)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        // Process payment
        $params = [
            'payment' => [
                'operation' => self::OPERATION_PURCHASE,
                'intent' => self::INTENT_AUTHORIZATION,
                'currency' => $order->getCurrency(),
                'prices' => [
                    [
                        'type' => 'Vipps',
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
                    'termsOfServiceUrl' => $this->configuration->getTermsUrl(),
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'riskIndicator' => $this->getRiskIndicator($orderId)->toArray(),
                'prefillInfo' => [
                    'msisdn' => $phone
                ],
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
            ]
        ];

        try {
            $result = $this->request('POST', '/psp/vipps/payments', $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }
}
