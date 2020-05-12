<?php

namespace SwedbankPay\Core;

/**
 * Class OrderItem
 *
 * @method mixed getReference()
 * @method $this setReference($value)
 * @method mixed getName()
 * @method $this setName($value)
 * @method mixed getType()
 * @method $this setType($value)
 * @method mixed getClass()
 * @method $this setClass($value)
 * @method mixed getDescription()
 * @method $this setDescription($value)
 * @method mixed getAmount()
 * @method $this setAmount($value)
 */
class OrderItem extends Data implements OrderItemInterface
{
    /**
     * OrderItem constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->setData($data);
    }

    /**
     * Get Item Url
     *
     * @return string|null
     */
    public function getItemUrl()
    {
        return $this->getData(OrderItemInterface::FIELD_ITEM_URL);
    }

    /**
     * Set Item Url
     *
     * @param string $url
     *
     * @return $this
     */
    public function setItemUrl($url)
    {
        return $this->setData(OrderItemInterface::FIELD_ITEM_URL, $url);
    }

    /**
     * Get Image Url
     *
     * @return string|null
     */
    public function getImageUrl()
    {
        return $this->getData(OrderItemInterface::FIELD_IMAGE_URL);
    }

    /**
     * Set Image Url
     *
     * @param string $url
     *
     * @return $this
     */
    public function setImageUrl($url)
    {
        return $this->setData(OrderItemInterface::FIELD_IMAGE_URL, $url);
    }

    /**
     * Get Qty
     *
     * @return int
     */
    public function getQty()
    {
        return $this->getData(OrderItemInterface::FIELD_QTY);
    }

    /**
     * Set Qty
     *
     * @param int $qty
     *
     * @return $this
     */
    public function setQty($qty)
    {
        return $this->setData(OrderItemInterface::FIELD_QTY, $qty);
    }

    /**
     * Get Qty Unit
     *
     * @return string
     */
    public function getQtyUnit()
    {
        return $this->getData(OrderItemInterface::FIELD_QTY_UNIT);
    }

    /**
     * Set Qty Unit
     *
     * @param string $qtyUnit
     *
     * @return $this
     */
    public function setQtyUnit($qtyUnit)
    {
        return $this->setData(OrderItemInterface::FIELD_QTY_UNIT, $qtyUnit);
    }

    /**
     * Get Unit Price
     *
     * @return mixed
     */
    public function getUnitPrice()
    {
        return $this->getData(OrderItemInterface::FIELD_UNITPRICE);
    }

    /**
     * Set Unit Price
     *
     * @param mixed $price
     *
     * @return $this
     */
    public function setUnitPrice($price)
    {
        return $this->setData(OrderItemInterface::FIELD_UNITPRICE, $price);
    }

    /**
     * Get Vat Percent
     *
     * @return mixed
     */
    public function getVatPercent()
    {
        return $this->getData(OrderItemInterface::FIELD_VAT_PERCENT);
    }

    /**
     * Set Vat Percent
     *
     * @param mixed $percent
     *
     * @return $this
     */
    public function setVatPercent($percent)
    {
        return $this->setData(OrderItemInterface::FIELD_VAT_PERCENT, $percent);
    }

    /**
     * Get Vat Amount
     *
     * @return mixed
     */
    public function getVatAmount()
    {
        return $this->getData(OrderItemInterface::FIELD_VAT_AMOUNT);
    }

    /**
     * Set Vat Amount
     *
     * @param mixed $vat
     *
     * @return $this
     */
    public function setVatAmount($vat)
    {
        return $this->setData(OrderItemInterface::FIELD_VAT_AMOUNT, $vat);
    }

}
