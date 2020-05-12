<?php

namespace SwedbankPay\Core\Order;

interface RiskIndicatorInterface
{
    const DELIVERY_EMAIL_ADDRESS = 'deliveryEmailAddress';
    const DELIVERY_TIME_FRAME_INDICATOR = 'deliveryTimeFrameIndicator';
    const SHIP_INDICATOR = 'shipIndicator';
}
