<?php

namespace Smartling\Tuner;

use Smartling\Helpers\SimpleStorageHelper;

abstract class CustomizationManagerAbstract implements ManagerInterface, \Iterator, \ArrayAccess
{
    private string $storageKey = '';
    protected array $state = [];

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

    public function listItems(): array
    {
        return $this->state;
    }

    public function updateItem(string $id, array $data): void
    {
        $this[$id] = $data;
    }

    public function removeItem(string $id): void
    {
        unset($this[$id]);
    }

    public function getItem(string $id): mixed
    {
        return $this[$id];
    }

    public function current(): mixed
    {
        return current($this->state);
    }

    public function next(): void
    {
        next($this->state);
    }

    public function key(): string|int|null
    {
        return key($this->state);
    }

    public function valid(): bool
    {
        return null !== $this->key();
    }

    public function rewind(): void
    {
        reset($this->state);
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->state);
    }

    public function offsetGet($offset): mixed
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
