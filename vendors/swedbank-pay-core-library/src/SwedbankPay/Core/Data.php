<?php

namespace SwedbankPay\Core;

use InvalidArgumentException;

class Data implements \ArrayAccess
{
    protected $data = [];

    /**
     * Check is data exists
     * @param $key
     * @return bool
     */
    public function hasData($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Get Data
     * @param mixed $key
     * @return array|mixed
     */
    public function getData($key = null)
    {
        if (!$key) {
            return $this->data;
        }

        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Set Data
     * @param $key
     * @param mixed|null $value
     * @return $this
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $key1 => $value1) {
                if (is_scalar($key1)) {
                    $this->setData($key1, $value1);
                }
            }
        } elseif (is_scalar($key)) {
            $this->data[$key] = $value;
        } else {
            throw new InvalidArgumentException(sprintf('Invalid type for index %s', var_export($key, true)));
        }

        return $this;
    }

    /**
     * Unset Data
     * @param $key
     * @return $this
     */
    public function unsData($key)
    {
        if ($this->hasData($key)) {
            unset($this->data[$key]);
        }

        return $this;
    }

    /**
     * Get Data as array
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Set/Get attribute wrapper
     *
     * @param string $method
     * @param array $args
     * @return  mixed
     */
    public function __call($method, $args)
    {
        switch (substr($method, 0, 3)) {
            case 'get' :
                $key = $this->_underscore(substr($method, 3));
                return $this->getData($key);
            case 'set' :
                $key = $this->_underscore(substr($method, 3));
                $this->setData($key, isset($args[0]) ? $args[0] : null);
                return $this;
            case 'uns' :
                $key = $this->_underscore(substr($method, 3));
                $this->unsData($key);
                return $this;
            case 'has' :
                $key = $this->_underscore(substr($method, 3));
                return $this->hasData($key);
        }

        throw new \Exception(sprintf('Invalid method %s::%s', get_class($this), $method));
    }

    /**
     * Implementation of \ArrayAccess::offsetSet()
     *
     * @param string $offset
     * @param mixed $value
     * @return void
     * @link http://www.php.net/manual/en/arrayaccess.offsetset.php
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * Implementation of \ArrayAccess::offsetExists()
     *
     * @param string $offset
     * @return bool
     * @link http://www.php.net/manual/en/arrayaccess.offsetexists.php
     */
    public function offsetExists($offset)
    {
        return $this->hasData($offset);
    }

    /**
     * Implementation of \ArrayAccess::offsetUnset()
     *
     * @param string $offset
     * @return void
     * @link http://www.php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset)
    {
        $this->unsData($offset);
    }

    /**
     * Implementation of \ArrayAccess::offsetGet()
     *
     * @param string $offset
     * @return mixed
     * @link http://www.php.net/manual/en/arrayaccess.offsetget.php
     */
    public function offsetGet($offset)
    {
        return $this->getData($offset);
    }

    /**
     * Converts field names for setters and getters
     *
     * @param string $name
     * @return string
     */
    protected function _underscore($name)
    {
        return strtolower(preg_replace('/(.)([A-Z])/', '$1_$2', $name));
    }
}
