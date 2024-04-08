<?php

namespace Smartling\Models;

class IntStringPairCollection {
    /**
     * @var IntStringPair[]
     */
    private array $array;
    /**
     * @param IntStringPair[]|string[] $items
     */
    public function __construct(array $items)
    {
        $array = [];
        foreach ($items as $key => $value) {
            if ($value instanceof IntStringPair) {
                $array[] = $value;
            } elseif (is_int($key) && is_string($value)) {
                $array[] = new IntStringPair($key, $value);
            } else {
                throw new \InvalidArgumentException("Items expected to be array of " . IntStringPair::class);
            }
        }
        $this->array = $array;
    }

    /**
     * @param IntStringPair[] $items
     */
    public function add(array $items): self
    {
        foreach ($items as $item) {
            if (!$item instanceof IntStringPair) {
                throw new \InvalidArgumentException("Items expected to be array of " . IntStringPair::class);
            }
        }
        return new self(array_merge($this->array, $items));
    }

    /**
     * @return IntStringPair[]
     */
    public function getArray(): array
    {
        return $this->array;
    }

    public function getList(): array
    {
        $result = [];
        foreach ($this->array as $intStringPair) {
            $result[] = $intStringPair->getValue();
        }

        return $result;
    }
}
