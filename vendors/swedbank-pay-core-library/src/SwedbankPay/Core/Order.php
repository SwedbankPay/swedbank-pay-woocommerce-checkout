<?php

namespace SwedbankPay\Core;

/**
 * Class Order
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
class Order extends Data implements OrderInterface
{
    /**
     * Order constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }

    /**
     * Get Amount In Cents.
     *
     * @return int
     */
    public function getAmountInCents()
    {
        return (int)bcmul(100, $this->getAmount());
    }

    public function getVatAmountInCents()
    {
        return (int)bcmul(100, $this->getVatAmount());
    }

    /**
     * Set Order Items
     *
     * @param array $items
     *
     * @return $this
     */
    public function setItems(array $items = [])
    {
        return $this->setData('items', $items);
    }

    /**
     * Get Order Items
     *
     * @return OrderItem[]
     */
    public function getItems()
    {
        $result = [];
        $items = $this->getData('items');

        if ($items) {
            foreach ($items as $item) {
                $result[] = new OrderItem($item);
            }
        }

        return $result;
    }

    /**
     * Payment Token Should be Saved
     *
     * @return bool
     */
    public function needsSaveToken()
    {
        return $this->getNeedsSaveTokenFlag();
    }

    /**
     * Get card holder's information.
     *
     * @return array
     */
    public function getCardHolderInformation()
    {
        return [
            'firstName' => $this->getBillingFirstName(),
            'lastName' => $this->getBillingLastName(),
            'email' => $this->getBillingEmail(),
            'msisdn' => $this->getBillingPhone(),
            'homePhoneNumber' => $this->getBillingPhone(),
            'workPhoneNumber' => $this->getBillingPhone(),
            'shippingAddress' => [
                'firstName' => $this->getShippingFirstName(),
                'lastName' => $this->getShippingLastName(),
                'email' => $this->getShippingEmail(),
                'msisdn' => $this->getShippingPhone(),
                'streetAddress' => implode(', ', [$this->getShippingAddress1(), $this->getShippingAddress2()]),
                'coAddress' => '',
                'city' => $this->getShippingCity(),
                'zipCode' => $this->getShippingPostcode(),
                'countryCode' => $this->getShippingCountryCode()
            ],
            'billingAddress' => [
                'firstName' => $this->getBillingFirstName(),
                'lastName' => $this->getBillingLastName(),
                'email' => $this->getBillingEmail(),
                'msisdn' => $this->getBillingPhone(),
                'streetAddress' => implode(', ', [$this->getBillingAddress1(), $this->getBillingAddress2()]),
                'coAddress' => '',
                'city' => $this->getBillingCity(),
                'zipCode' => $this->getBillingPostcode(),
                'countryCode' => $this->getBillingCountryCode()
            ],
        ];
    }
}
