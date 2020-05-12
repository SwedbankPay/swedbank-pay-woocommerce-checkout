<?php

namespace SwedbankPay\Core\Order;

use SwedbankPay\Core\Data;

/**
 * Class RiskIndicator
 * @package SwedbankPay\Core\Order
 */
class RiskIndicator extends Data implements RiskIndicatorInterface
{
    /**
     * RiskIndicator constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }
}
