<?php

namespace SwedbankPay\Core\Order;

interface PayeeInfoInterface
{
    // 	The ID of the payee, usually the merchant ID.
    const PAYEE_ID = 'payeeId';

    // A unique reference from the merchant system. It is set per operation to ensure
    // an exactly-once delivery of a transactional operation. Max 30
    const PAYEE_REFERENCE = 'payeeReference';

    // The name of the payee, usually the name of the merchant.
    const PAYEE_NAME = 'payeeName';

    // A product category or number sent in from the payee/merchant.
    // This is not validated by Swedbank Pay, but will be passed through the payment process and may be used in
    // the settlement process.
    const PRODUCT_CATEGORY = 'productCategory';

    // The order reference should reflect the order reference found in the merchant’s systems. Max 50
    const ORDER_REFERENCE = 'orderReference';

    // The subsite field can be used to perform [split settlement][split-settlement] on the payment. Max 40
    const SUBSITE = 'subsite';
}
