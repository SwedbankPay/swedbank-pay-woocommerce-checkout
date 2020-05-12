<?php

namespace SwedbankPay\Core;

use Throwable;

class Exception extends \Exception
{
    const ERROR_DECLINED = 1;

    public $problems = [];

    public function __construct($message = "", $code = 0, Throwable $previous = null, $problems = [])
    {
        $this->problems = $problems;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get Problems.
     *
     * @return array
     */
    public function getProblems()
    {
        return $this->problems;
    }
}
