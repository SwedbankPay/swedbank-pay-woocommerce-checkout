<?php

namespace SwedbankPay\Core\Order;

use SwedbankPay\Core\Data;

/**
 * Class PlatformUrls
 * @package SwedbankPay\Core\Order
 * @method string getCompleteUrl()
 * @method string getCancelUrl()
 * @method string getCallbackUrl()
 */
class PlatformUrls extends Data implements PlatformUrlsInterface
{
    /**
     * PlatformUrls constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }

    /**
     * Get urls where hosts
     *
     * @return array
     */
    public function getHostUrls()
    {
        $urls = [];

        foreach ($this->getData() as $key => $url) {
            if (filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)) {
                $parsed = parse_url($url);
                $urls[] = sprintf('%s://%s', $parsed['scheme'], $parsed['host']);
            }
        }

        return array_unique($urls);
    }
}
