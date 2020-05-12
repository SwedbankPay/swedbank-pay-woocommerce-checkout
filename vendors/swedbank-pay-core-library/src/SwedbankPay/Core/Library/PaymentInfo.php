<?php

namespace SwedbankPay\Core\Library;

use SwedbankPay\Core\Api\Authorization;
use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Api\Transaction;
use SwedbankPay\Core\Api\Verification;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;

trait PaymentInfo
{
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
    public function request($method, $url, $params = [])
    {
        // Get rid of full url. There's should be an endpoint only.
        if (filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)) {
            $parsed = parse_url($url);
            $url = $parsed['path'];
            if (!empty($parsed['query'])) {
                $url .= '?' . $parsed['query'];
            }
        }

        if (empty($url)) {
            throw new \Exception('Invalid url');
        }

        // Process params
        array_walk_recursive($params, function (&$input, $key) {
            if (is_object($input) && method_exists($input, 'toArray')) {
                $input = $input->toArray();
            }
        });

        $start = microtime(true);
        $this->log(LogLevel::DEBUG,
            sprintf('Request: %s %s %s', $method, $url, json_encode($params, JSON_PRETTY_PRINT)));

        try {
            /** @var \SwedbankPay\Api\Response $response */
            $client = $this->client->request($method, $url, $params);
            $response_body = $client->getResponseBody();
            $result = json_decode($response_body, true);
            $time = microtime(true) - $start;
            $this->log(LogLevel::DEBUG, sprintf('[%.4F] Response: %s', $time, $response_body),
                [$method, $url, $params]);

            return new Response($result);
        } catch (\SwedbankPay\Api\Client\Exception $e) {
            $time = microtime(true) - $start;
            $this->log(LogLevel::DEBUG,
                sprintf('[%.4F] Client Exception. Check debug info: %s', $time, $this->client->getDebugInfo()));

            // https://tools.ietf.org/html/rfc7807
            $data = @json_decode($this->client->getResponseBody(), true);
            if (json_last_error() === JSON_ERROR_NONE &&
                isset($data['title']) &&
                isset($data['detail'])
            ) {
                // Format error message
                $message = sprintf('%s. %s', $data['title'], $data['detail']);

                // Get details
                if (isset($data['problems'])) {
                    $detailed = '';
                    $problems = $data['problems'];
                    foreach ($problems as $problem) {
                        $detailed .= sprintf('%s: %s', $problem['name'], $problem['description']) . "\r\n";
                    }

                    if (!empty($detailed)) {
                        $message .= "\r\n" . $detailed;
                    }
                }

                throw new Exception($message, 0, null, $data['problems']);
            }

            throw new Exception('API Exception. Please check logs');
        }
    }

    /**
     * Fetch Payment Info.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Response
     * @throws Exception
     */
    public function fetchPaymentInfo($paymentIdUrl, $expand = null)
    {
        if ($expand) {
            $paymentIdUrl .= '?$expand=' . $expand;
        }

        try {
            $result = $this->request('GET', $paymentIdUrl);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * Fetch Transaction List.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Transaction[]
     * @throws Exception
     */
    public function fetchTransactionsList($paymentIdUrl, $expand = null)
    {
        $paymentIdUrl .= '/transactions';

        if ($expand) {
            $paymentIdUrl .= '?$expand=' . $expand;
        }

        try {
            $result = $this->request('GET', $paymentIdUrl);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        $transactions = [];
        foreach ($result['transactions']['transactionList'] as $transaction) {
            $transactions[] = new Transaction($transaction);
        }

        return $transactions;
    }

    /**
     * Fetch Verification List.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Verification[]
     * @throws Exception
     */
    public function fetchVerificationList($paymentIdUrl, $expand = null)
    {
        $paymentIdUrl .= '/verifications';

        if ($expand) {
            $paymentIdUrl .= '?$expand=' . $expand;
        }

        try {
            $result = $this->request('GET', $paymentIdUrl);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        $verifications = [];
        foreach ($result['verifications']['verificationList'] as $verification) {
            $verifications[] = new Verification($verification);
        }

        return $verifications;
    }

    /**
     * Fetch Authorization List.
     *
     * @param string $paymentIdUrl
     * @param string|null $expand
     *
     * @return Authorization[]
     * @throws Exception
     */
    public function fetchAuthorizationList($paymentIdUrl, $expand = null)
    {
        $paymentIdUrl .= '/authorizations';

        if ($expand) {
            $paymentIdUrl .= '?$expand=' . $expand;
        }

        try {
            $result = $this->request('GET', $paymentIdUrl);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        $authorizations = [];
        foreach ($result['authorizations']['authorizationList'] as $authorization) {
            $authorizations[] = new Authorization($authorization);
        }

        return $authorizations;
    }
}
