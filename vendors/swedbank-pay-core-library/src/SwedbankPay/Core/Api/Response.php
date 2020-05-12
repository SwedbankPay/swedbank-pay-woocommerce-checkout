<?php

namespace SwedbankPay\Core\Api;

use SwedbankPay\Core\Data;

class Response extends Data
{
    /**
     * Response constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }

    /**
     * Extract operation value from operations list
     *
     * @param string $rel
     * @param bool $single
     *
     * @return bool|string|array
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function getOperationByRel($rel, $single = true)
    {
        $operations = $this->hasData('operations') ? $this->getData('operations') : [];
        $operation = array_filter($operations, function ($value, $key) use ($rel) {
            return (is_array($value) && $value['rel'] === $rel);
        }, ARRAY_FILTER_USE_BOTH);

        if (count($operation) > 0) {
            $operation = array_shift($operation);

            return $single ? $operation['href'] : $operation;
        }

        return false;
    }
}