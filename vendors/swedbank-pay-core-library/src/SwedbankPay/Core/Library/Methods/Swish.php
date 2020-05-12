<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;

trait Swish
{
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
    public function initiateSwishPayment($orderId, $phone, $ecomOnlyEnabled = true)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        // Process payment
        $params = [
            'payment' => [
                'operation' => self::OPERATION_PURCHASE,
                'intent' => self::INTENT_SALE,
                'currency' => $order->getCurrency(),
                'prices' => [
                    [
                        'type' => 'Swish',
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
                    // 50px height and 400px width. Require https.
                    //'logoUrl'     => "https://example.com/logo.png",// @todo
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'riskIndicator' => $this->getRiskIndicator($orderId)->toArray(),
                'prefillInfo' => [
                    'msisdn' => $phone
                ],
                'swish' => [
                    'ecomOnlyEnabled' => $ecomOnlyEnabled
                ],
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
            ]
        ];

        try {
            $result = $this->request('POST', '/psp/swish/payments', $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * initiate Swish Payment Direct
     *
     * @param string $saleHref
     * @param string $phone
     *
     * @return mixed
     * @throws Exception
     */
    public function initiateSwishPaymentDirect($saleHref, $phone)
    {
        $params = [
            'transaction' => [
                'msisdn' => $phone
            ]
        ];

        try {
            $result = $this->request('POST', $saleHref, $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

}
