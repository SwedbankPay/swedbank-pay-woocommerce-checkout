<?php

namespace SwedbankPay\Core;


/**
 * Interface OrderInterface
 * @package SwedbankPay\Core
 * @method mixed getOrderId()
 * @method float getAmount()
 * @method float getVatAmount()
 * @method float getVatRate()
 * @method $this setShippingAmount($value)
 * @method mixed getShippingAmount()
 * @method $this setShippingVatAmount($value)
 * @method mixed getShippingVatAmount()
 * @method $this setShippingVatRate($value)
 * @method mixed getShippingVatRate()
 * @method string getCurrency()
 * @method $this setCurrency($currency)
 * @method string getDescription()
 * @method $this setDescription($description)
 * @method string getCustomerId()
 * @method $this setCustomerId($customerId)
 * @method mixed getStatus()
 * @method $this setStatus($value)
 * @method $this setCreatedAt($value)
 * @method mixed getCreatedAt()
 * @method $this setLanguage($value)
 * @method mixed getLanguage()
 * @method $this setPaymentId($value)
 * @method mixed getPaymentId()
 * @method $this setPaymentOrderId($value)
 * @method mixed getPaymentOrderId()
 * @method $this setNeedsSaveTokenFlag($value)
 * @method bool getNeedsSaveTokenFlag()
 * @method $this setHttpAccept($value)
 * @method $this setHttpUserAgent($value)
 * @method mixed getHttpUserAgent()
 * @method $this setCustomerIp($value)
 * @method mixed getCustomerIp()
 * @method $this setPayerReference($value)
 * @method mixed getPayerReference()
 * @method $this setBillingCountryCode($value)
 * @method string getBillingCountryCode()
 * @method $this setBillingCountry($value)
 * @method mixed getBillingCountry()
 * @method $this setBillingAddress1($value)
 * @method mixed getBillingAddress1()
 * @method $this setBillingAddress2($value)
 * @method mixed getBillingAddress2()
 * @method $this setBillingAddress3($value)
 * @method mixed getBillingAddress3()
 * @method $this setBillingCity($value)
 * @method mixed getBillingCity()
 * @method $this setBillingState($value)
 * @method mixed getBillingState()
 * @method $this setBillingPostcode($value)
 * @method mixed getBillingPostcode()
 * @method $this setBillingPhone($value)
 * @method mixed getBillingPhone()
 * @method $this setBillingFax($value)
 * @method mixed getBillingFax()
 * @method $this setBillingEmail($value)
 * @method mixed getBillingEmail()
 * @method $this setBillingFirstName($value)
 * @method mixed getBillingFirstName()
 * @method $this setBillingLastName($value)
 * @method mixed getBillingLastName()
 * @method $this setBillingStreetNumber($value)
 * @method mixed getBillingStreetNumber()
 * @method $this setShippingCountryCode($value)
 * @method string getShippingCountryCode()
 * @method $this setShippingCountry($value)
 * @method mixed getShippingCountry()
 * @method $this setShippingCounty($value)
 * @method mixed getShippingCounty()
 * @method $this setShippingAddress1($value)
 * @method mixed getShippingAddress1()
 * @method $this setShippingAddress2($value)
 * @method mixed getShippingAddress2()
 * @method $this setShippingAddress3($value)
 * @method mixed getShippingAddress3()
 * @method $this setShippingCity($value)
 * @method mixed getShippingCity()
 * @method $this setShippingState($value)
 * @method mixed getShippingState()
 * @method $this setShippingPostcode($value)
 * @method mixed getShippingPostcode()
 * @method $this setShippingPhone($value)
 * @method mixed getShippingPhone()
 * @method $this setShippingFax($value)
 * @method mixed getShippingFax()
 * @method $this setShippingEmail($value)
 * @method mixed getShippingEmail()
 * @method $this setShippingFirstName($value)
 * @method mixed getShippingFirstName()
 * @method $this setShippingLastName($value)
 * @method mixed getShippingLastName()
 */
interface OrderInterface
{
    /**
     * Order Fields
     */
    const ITEMS = 'items';
    const LANGUAGE = 'language';
    const ORDER_ID = 'order_id';
    const CURRENCY = 'currency';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';
    const DESCRIPTION = 'description';
    const HTTP_ACCEPT = 'http_accept';
    const HTTP_USER_AGENT = 'http_user_agent';

    const PAYMENT_ID = 'payment_id';
    const PAYMENT_ORDER_ID = 'payment_order_id';

    /**
     * Totals
     */
    const AMOUNT = 'amount';
    const VAT_AMOUNT = 'vat_amount';
    const VAT_RATE = 'vat_rate';
    const SHIPPING_AMOUNT = 'shipping_amount';
    const SHIPPING_VAT_AMOUNT = 'shipping_vat_amount';
    const SHIPPING_VAT_RATE = 'shipping_vat_rate';
    const NEEDS_SAVE_TOKEN_FLAG = 'needs_save_token_flag';

    /**
     * Customer Fields
     */
    const CUSTOMER_ID = 'customer_id';
    const CUSTOMER_IP = 'customer_ip';
    const PAYER_REFERENCE = 'payer_reference';

    /**
     * Billing Address Fields
     */
    const BILLING_COUNTRY = 'billing_country';
    const BILLING_COUNTRY_CODE = 'billing_country_code';
    const BILLING_ADDRESS1 = 'billing_address1';
    const BILLING_ADDRESS2 = 'billing_address2';
    const BILLING_ADDRESS3 = 'billing_address3';
    const BILLING_CITY = 'billing_city';
    const BILLING_STATE = 'billing_state';
    const BILLING_POSTCODE = 'billing_postcode';
    const BILLING_PHONE = 'billing_phone';
    const BILLING_FAX = 'billing_fax';
    const BILLING_EMAIL = 'billing_email';
    const BILLING_FIRST_NAME = 'billing_first_name';
    const BILLING_LAST_NAME = 'billing_last_name';

    /**
     * Shipping Address Fields
     */
    const SHIPPING_COUNTRY = 'shipping_country';
    const SHIPPING_COUNTRY_CODE = 'shipping_country_code';
    const SHIPPING_ADDRESS1 = 'shipping_address1';
    const SHIPPING_ADDRESS2 = 'shipping_address2';
    const SHIPPING_ADDRESS3 = 'shipping_address3';
    const SHIPPING_CITY = 'shipping_city';
    const SHIPPING_STATE = 'shipping_state';
    const SHIPPING_POSTCODE = 'shipping_postcode';
    const SHIPPING_PHONE = 'shipping_phone';
    const SHIPPING_FAX = 'shipping_fax';
    const SHIPPING_EMAIL = 'shipping_email';
    const SHIPPING_FIRST_NAME = 'shipping_first_name';
    const SHIPPING_LAST_NAME = 'shipping_last_name';

    /**
     * Payment Statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_CAPTURED = 'captured';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_FAILED = 'failed';
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Get Amount In Cents.
     *
     * @return int
     */
    public function getAmountInCents();

    public function getVatAmountInCents();

    /**
     * Set Order Items
     *
     * @param array $items
     *
     * @return $this
     */
    public function setItems(array $items = []);

    /**
     * Get Order Items
     *
     * @return OrderItem[]
     */
    public function getItems();

    /**
     * Payment Token Should be Saved
     *
     * @return bool
     */
    public function needsSaveToken();

    /**
     * Get card holder's information.
     *
     * @return array
     */
    public function getCardHolderInformation();
}