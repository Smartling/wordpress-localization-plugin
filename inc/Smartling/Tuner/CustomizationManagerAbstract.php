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
    private string $storageKey = '';

    /**
     * @var array
     */
    private array $state = [];

    protected function generateId(int $length = 12): string
    {
        return substr(md5(microtime(true)), 0, $length);
    }

    public function __construct($storageKey)
    {
        $this->setStorageKey($storageKey);
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function setStorageKey(string $storageKey): void
    {
        $this->storageKey = $storageKey;
    }

    public function loadData(): void
    {
        $this->state = SimpleStorageHelper::get($this->getStorageKey(), []);
    }

    public function saveData(): void
    {
        SimpleStorageHelper::set($this->getStorageKey(), $this->state);
    }

    /**
     * @return array[]
     */
    public function listItems(): array
    {
        return $this->state;
    }

    /**
     * @param string $id
     */
    public function updateItem($id, array $data): void
    {
        $this[$id] = $data;
    }

    /**
     * @param string $id
     */
    public function removeItem($id): void
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

    public function current()
    {
        return current($this->state);
    }

    public function next()
    {
        return next($this->state);
    }

    public function key()
    {
        return key($this->state);
    }

    public function valid(): bool
    {
        return null !== $this->key();
    }

    public function rewind()
    {
        return reset($this->state);
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->state);
    }

    public function offsetGet($offset)
    {
        if (isset($this[$offset])) {
            return $this->state[$offset];
        }

        throw new \InvalidArgumentException(vsprintf('Invalid index "%s" used', [$offset]));
    }

    public function offsetSet($offset, $value): void
    {
        $this->state[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        if (isset($this[$offset])) {
            unset($this->state[$offset]);
        }
    }

    public function add(array $value): string
    {
        $id = $this->generateId();
        $this[$id] = $value;

        return $id;
    }
}
