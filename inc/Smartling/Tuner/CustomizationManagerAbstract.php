<?php

namespace Smartling\Tuner;

use Smartling\Helpers\SimpleStorageHelper;

/**
 * Class CustomizationManagerAbstract
 * @package Smartling\Tuner
 */
abstract class CustomizationManagerAbstract implements ManagerInterface, \Iterator, \ArrayAccess
{
    /**
     * @var string
     */
    private $storageKey = '';

    /**
     * @var array
     */
    private $state = [];

    /**
     * @param int $length
     *
     * @return string
     */
    protected function generateId($length = 12)
    {
        return substr(md5(microtime(true)), 0, $length);
    }

    public function __construct($storageKey)
    {
        $this->setStorageKey($storageKey);
    }

    /**
     * @return string
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }

    /**
     * @param string $storageKey
     */
    public function setStorageKey($storageKey)
    {
        $this->storageKey = $storageKey;
    }

    public function loadData()
    {
        $this->state = SimpleStorageHelper::get($this->getStorageKey(), []);
    }

    public function saveData()
    {
        SimpleStorageHelper::set($this->getStorageKey(), $this->state);
    }

    /**
     * @return array[]
     */
    public function listItems()
    {
        return $this->state;
    }

    /**
     * @param string $id
     * @param array  $data
     *
     * @return mixed
     */
    public function updateItem($id, array $data)
    {
        $this[$id] = $data;
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function removeItem($id)
    {
        unset($this[$id]);
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function getItem($id)
    {
        return $this[$id];
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return current($this->state);
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        return next($this->state);
    }

    /**
     * @return mixed
     */
    public function key()
    {
        return key($this->state);
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return null !== $this->key();
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        return reset($this->state);
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->state);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        if (isset($this[$offset])) {
            return $this->state[$offset];
        } else {
            throw new \InvalidArgumentException(vsprintf('Invalid index "%s" used', [$offset]));
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        $this->state[$offset] = $value;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        if (isset($this[$offset])) {
            unset($this->state[$offset]);
        }
    }

    public function add(array $value)
    {
        $id = $this->generateId();
        $this[$id] = $value;

        return $id;
    }

}
