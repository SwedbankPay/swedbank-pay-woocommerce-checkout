<?php

namespace SwedbankPay\Core;

interface OrderItemInterface
{
    /**
     * Item Types
     */
    const TYPE_PRODUCT = 'PRODUCT';
    const TYPE_SHIPPING = 'SHIPPING_FEE';
    const TYPE_DISCOUNT = 'DISCOUNT';
    const TYPE_OTHER = 'OTHER';

    /**
     * Items Fields
     */
    const FIELD_REFERENCE = 'reference';
    const FIELD_NAME = 'name';
    const FIELD_TYPE = 'type';
    const FIELD_CLASS = 'class';
    const FIELD_ITEM_URL = 'itemUrl';
    const FIELD_IMAGE_URL = 'imageUrl';
    const FIELD_DESCRIPTION = 'description';
    const FIELD_QTY = 'quantity';
    const FIELD_QTY_UNIT = 'quantityUnit';
    const FIELD_UNITPRICE = 'unitPrice';
    const FIELD_VAT_PERCENT = 'vatPercent';
    const FIELD_AMOUNT = 'amount';
    const FIELD_VAT_AMOUNT = 'vatAmount';
}
