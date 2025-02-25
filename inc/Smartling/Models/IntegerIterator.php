<?php

namespace Smartling\Models;

class IntegerIterator extends \ArrayIterator
{
    private const string SEPARATOR = ',';

    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            if (!is_int($item)) {
                throw new \InvalidArgumentException('Items expected to be array of integers');
            }
        }
        parent::__construct($items);
    }

    public function append(mixed $value): void
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException('Value expected to be integer');
        }
        parent::append($value);
    }

    public function current(): int
    {
        return parent::current();
    }

    public function serialize(): string
    {
        return implode(self::SEPARATOR, $this->getArrayCopy());
    }

    public function offsetGet(mixed $key): int
    {
        return parent::offsetGet($key);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException('Value expected to be integer');
        }
        parent::offsetSet($key, $value);
    }

    public static function fromString(string $data): self
    {
        return new self(array_map(static fn($item) => (int)$item, explode(self::SEPARATOR, $data)));
    }
}
