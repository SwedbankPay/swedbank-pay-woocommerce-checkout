<?php

namespace SwedbankPay\Core\Order;

interface PlatformUrlsInterface
{
    const COMPLETE_URL = 'complete_url';
    const CANCEL_URL = 'cancel_url';
    const CALLBACK_URL = 'callback_url';
    const TERMS_URL = 'terms_url';
}
