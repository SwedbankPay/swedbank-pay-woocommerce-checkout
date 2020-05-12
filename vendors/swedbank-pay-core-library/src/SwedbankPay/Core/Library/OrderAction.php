<?php

namespace SwedbankPay\Core\Library;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Api\Transaction;
use SwedbankPay\Core\Api\TransactionInterface;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Order;
use SwedbankPay\Core\OrderInterface;

trait OrderAction
{
    /**
     * Can Capture.
     *
     * @param mixed $orderId
     * @param float|int|null $amount
     *
     * @return bool
     */
    public function canCapture($orderId, $amount = null)
    {
        // Check the payment state online
        $order = $this->getOrder($orderId);

        // Fetch payment info
        try {
            $result = $this->fetchPaymentInfo($order->getPaymentId());
        } catch (\Exception $e) {
            // Request failed
            return false;
        }

        return isset($result['payment']['remainingCaptureAmount']) && (float)$result['payment']['remainingCaptureAmount'] > 0.1;
    }

    /**
     * Can Cancel.
     *
     * @param mixed $orderId
     * @param float|int|null $amount
     *
     * @return bool
     */
    public function canCancel($orderId, $amount = null)
    {
        // Check the payment state online
        $order = $this->getOrder($orderId);

        // Fetch payment info
        try {
            $result = $this->fetchPaymentInfo($order->getPaymentId());
        } catch (\Exception $e) {
            // Request failed
            return false;
        }

        return isset($result['payment']['remainingCancellationAmount']) && (float)$result['payment']['remainingCancellationAmount'] > 0.1;
    }

    /**
     * Can Refund.
     *
     * @param mixed $orderId
     * @param float|int|null $amount
     *
     * @return bool
     */
    public function canRefund($orderId, $amount = null)
    {
        $order = $this->getOrder($orderId);

        if (!$amount) {
            $amount = $order->getAmount();
        }

        // Should has payment id
        $paymentId = $order->getPaymentId();
        if (!$paymentId) {
            return false;
        }

        // Should be captured
        // @todo Check payment state

        // Check refund amount
        $result = $this->fetchTransactionsList($order->getPaymentId());

        $refunded = 0;
        foreach ($result['transactions']['transactionList'] as $key => $transaction) {
            if ($transaction['type'] === 'Reversal') {
                $refunded += ($transaction['amount'] / 100);
            }
        }

        $possibleToRefund = $order->getAmount() - $refunded;
        if ($amount > $possibleToRefund) {
            return false;
        }

        return true;
    }

    /**
     * Capture.
     *
     * @param mixed $orderId
     * @param mixed $amount
     * @param mixed $vatAmount
     *
     * @return Response
     * @throws Exception
     */
    public function capture($orderId, $amount = null, $vatAmount = 0)
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

        // Checkout method can use the Invoice method
        $info = $this->fetchPaymentInfo($paymentId);
        if ($info['payment']['instrument'] === 'Invoice') {
            // @todo Should we use different credentials?
            return $this->captureInvoice($orderId, $amount, $vatAmount);
        }

        $href = $info->getOperationByRel('create-capture');
        if (empty($href)) {
            throw new Exception('Capture is unavailable');
        }

        $params = [
            'transaction' => [
                'amount' => (int)bcmul(100, $amount),
                'vatAmount' => (int)bcmul(100, $vatAmount),
                'description' => sprintf('Capture for Order #%s', $order->getOrderId()),
                'payeeReference' => $this->generatePayeeReference($orderId)
            ]
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
     * Cancel.
     *
     * @param mixed $orderId
     * @param mixed $amount
     * @param mixed $vatAmount
     *
     * @return Response
     * @throws Exception
     */
    public function cancel($orderId, $amount = null, $vatAmount = 0)
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

        // Checkout method can use the Invoice method
        $info = $this->fetchPaymentInfo($paymentId);
        if ($info['payment']['instrument'] === 'Invoice') {
            // @todo Should we use different credentials?
            return $this->cancelInvoice($orderId, $amount, $vatAmount);
        }

        $href = $info->getOperationByRel('create-cancellation');
        if (empty($href)) {
            throw new Exception('Cancellation is unavailable');
        }

        $params = [
            'transaction' => [
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
     * Refund.
     *
     * @param mixed $orderId
     * @param mixed $amount
     * @param mixed $vatAmount
     *
     * @return Response
     * @throws Exception
     */
    public function refund($orderId, $amount = null, $vatAmount = 0)
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

        // Checkout method can use the Invoice method
        $info = $this->fetchPaymentInfo($paymentId);
        if ($info['payment']['instrument'] === 'Invoice') {
            // @todo Should we use different credentials?
            return $this->refundInvoice($orderId, $amount, $vatAmount);
        }

        $href = $info->getOperationByRel('create-reversal');
        if (empty($href)) {
            throw new Exception('Refund is unavailable');
        }

        $params = [
            'transaction' => [
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

    /**
     * Abort Payment.
     *
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function abort($orderId)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $paymentId = $order->getPaymentId();
        if (empty($paymentId)) {
            throw new Exception('Unable to get payment ID');
        }

        // @todo Check if order has been paid
        $href = $this->fetchPaymentInfo($paymentId)->getOperationByRel('update-payment-abort');
        if (empty($href)) {
            throw new Exception('Abort is unavailable');
        }

        $params = [
            'payment' => [
                'operation' => 'Abort',
                'abortReason' => 'CancelledByConsumer'
            ]
        ];
        $result = $this->request('PATCH', $href, $params);

        if ($result['payment']['state'] === 'Aborted') {
            $this->updateOrderStatus(OrderInterface::STATUS_CANCELLED, 'Payment aborted');
        } else {
            throw new Exception('Aborting is failed.');
        }

        return $result;
    }

    /**
     * Update Order Status.
     *
     * @param mixed $orderId
     * @param string $status
     * @param string|null $message
     * @param string|null $transactionId
     */
    public function updateOrderStatus($orderId, $status, $message = null, $transactionId = null)
    {
        $this->adapter->updateOrderStatus($orderId, $status, $message, $transactionId);
    }

    /**
     * Analyze the transaction and update the related order.
     *
     * @param $orderId
     * @param Transaction|array $transaction
     *
     * @throws Exception
     */
    public function processTransaction($orderId, $transaction)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        if (is_array($transaction)) {
            $transaction = new Transaction($transaction);
        } elseif (!$transaction instanceof Transaction) {
            throw new \InvalidArgumentException('Invalid a transaction parameter');
        }

        // Apply action
        switch ($transaction->getType()) {
            case TransactionInterface::TYPE_AUTHORIZATION:
                if ($transaction->isFailed()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_FAILED,
                        sprintf('Authorization has been failed. Reason: %s.', $transaction->getFailedDetails()),
                        $transaction->getNumber()
                    );

                    break;
                }

                if ($transaction->isPending()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_AUTHORIZED,
                        'Authorization is pending.',
                        $transaction->getNumber()
                    );

                    break;
                }

                $this->updateOrderStatus(
                    $orderId,
                    OrderInterface::STATUS_AUTHORIZED,
                    'Payment has been authorized.',
                    $transaction->getNumber()
                );

                // Save Payment Token
                if ($order->needsSaveToken()) {
                    $authorizations = $this->fetchAuthorizationList($order->getPaymentId());
                    foreach ($authorizations as $authorization) {
                        if ($authorization->getPaymentToken() || $authorization->getRecurrenceToken()) {
                            // Add payment token
                            $this->adapter->savePaymentToken(
                                $order->getCustomerId(),
                                $authorization->getPaymentToken(),
                                $authorization->getRecurrenceToken(),
                                $authorization->getCardBrand(),
                                $authorization->getMaskedPan(),
                                $authorization->getExpireDate(),
                                $order->getOrderId()
                            );

                            // Use the first item only
                            break;
                        }
                    }
                }

                break;
            case TransactionInterface::TYPE_CAPTURE:
            case TransactionInterface::TYPE_SALE:
                if ($transaction->isFailed()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_FAILED,
                        sprintf('Capture has been failed. Reason: %s.', $transaction->getFailedDetails()),
                        $transaction->getNumber()
                    );

                    break;
                }

                if ($transaction->isPending()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_AUTHORIZED,
                        'Capture is pending.',
                        $transaction->getNumber()
                    );

                    break;
                }

                $this->updateOrderStatus(
                    $orderId,
                    OrderInterface::STATUS_CAPTURED,
                    'Payment has been captured.',
                    $transaction->getNumber()
                );
                break;
            case TransactionInterface::TYPE_CANCELLATION:
                if ($transaction->isFailed()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_FAILED,
                        sprintf('Cancellation has been failed. Reason: %s.', $transaction->getFailedDetails()),
                        $transaction->getNumber()
                    );

                    break;
                }

                if ($transaction->isPending()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_CANCELLED,
                        'Cancellation is pending.',
                        $transaction->getNumber()
                    );

                    break;
                }

                $this->updateOrderStatus(
                    $orderId,
                    OrderInterface::STATUS_CAPTURED,
                    'Payment has been cancelled.',
                    $transaction->getNumber()
                );
                break;
            case TransactionInterface::TYPE_REVERSAL:
                if ($transaction->isFailed()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_FAILED,
                        sprintf('Reversal has been failed. Reason: %s.', $transaction->getFailedDetails()),
                        $transaction->getNumber()
                    );

                    break;
                }

                if ($transaction->isPending()) {
                    $this->updateOrderStatus(
                        $orderId,
                        OrderInterface::STATUS_REFUNDED,
                        'Reversal is pending.',
                        $transaction->getNumber()
                    );

                    break;
                }

                $this->updateOrderStatus(
                    $orderId,
                    OrderInterface::STATUS_REFUNDED,
                    'Payment has been refunded.',
                    $transaction->getNumber()
                );
                break;
            default:
                throw new Exception(sprintf('Error: Unknown type %s', $transaction->getType()));
        }

    }

    /**
     * Generate Payee Reference for Order.
     *
     * @param mixed $orderId
     *
     * @return string
     */
    public function generatePayeeReference($orderId)
    {
        // Use the reference from the adapter if exists
        if (method_exists($this->adapter, 'generatePayeeReference')) {
            return $this->adapter->generatePayeeReference($orderId);
        }

        $arr = range('a', 'z');
        shuffle($arr);

        return $orderId . 'x' . substr(implode('', $arr), 0, 5);
    }
}