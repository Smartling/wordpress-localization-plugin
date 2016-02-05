<?php

namespace Smartling\Helpers;

/**
 * Class ProtectedArrayHelper
 *
 * @package Smartling\Helpers
 */
class ProtectedArrayHelper implements \ArrayAccess, \Iterator, \Countable
{

    /**
     * @var array
     */
    private $array = [];

    /**
     * ProtectedArrayHelper constructor.
     *
     * @param array $array
     */
    protected function __construct(array & $array)
    {
        $this->array = &$array;
    }

    /**
     * @param array $array
     *
     * @return ProtectedArrayHelper
     */
    public static function getProtectedArray(array & $array)
    {
        if (0 < count($array)) {
            return new self($array);
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->array;
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return current($this->array);
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        return next($this->array);;
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return !is_null($this->key());
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return key($this->array);
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        reset($this->array);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->array[$offset] : null;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->array);
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        if ($this->offsetExists($offset)) {
            $this->array[$offset] = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        return count($this->array);
    }
}