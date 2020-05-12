<?php

namespace SwedbankPay\Core\Order;

use SwedbankPay\Core\Data;

/**
 * Class PayeeData
 * @package SwedbankPay\Core\Order
 */
class PayeeInfo extends Data implements PayeeInfoInterface
{
    /**
     * PayeeInfo constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }
}
