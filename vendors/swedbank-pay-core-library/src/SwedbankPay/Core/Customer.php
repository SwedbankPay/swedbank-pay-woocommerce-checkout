<?php

namespace SwedbankPay\Core;

/**
 * Class Customer
 * @package SwedbankPay\Core
 */
class Customer extends Data implements CustomerInterface
{
    /**
     * Customer constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }
}
