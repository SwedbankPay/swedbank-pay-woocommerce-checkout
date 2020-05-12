<?php

namespace SwedbankPay\Core;

/**
 * Class Configuration
 * @package SwedbankPay\Core
 * @method bool getDebug()
 * @method string getMerchantToken()
 * @method string getPayeeId()
 * @method string getPayeeName()
 * @method bool getMode()
 * @method bool getAutoCapture()
 * @method string getSubsite()
 * @method string getLanguage()
 * @method string getSaveCC()
 * @method string getTermsUrl()
 * @method bool getRejectCreditCards()
 * @method bool getRejectDebitCards()
 * @method bool getRejectConsumerCards()
 * @method bool getRejectCorporateCards()
 */
class Configuration extends Data implements ConfigurationInterface
{
    /**
     * Configuration constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }
}
