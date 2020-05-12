<?php

namespace SwedbankPay\Core;

/**
 * Interface ConfigurationInterface
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
interface ConfigurationInterface
{
    const MERCHANT_TOKEN = 'merchant_token';
    const PAYEE_ID = 'payee_id';
    const PAYEE_NAME = 'payee_name';
    const MODE = 'mode';
    const AUTO_CAPTURE = 'auto_capture';
    const SUBSITE = 'subsite';
    const DEBUG = 'debug';
    const LANGUAGE = 'language';
    const SAVE_CC = 'save_cc';
    const TERMS_URL = 'terms_url';

    const REJECT_CREDIT_CARDS = 'reject_credit_cards';
    const REJECT_DEBIT_CARDS = 'reject_debit_cards';
    const REJECT_CONSUMER_CARDS = 'reject_consumer_cards';
    const REJECT_CORPORATE_CARDS = 'reject_corporate_cards';
}
