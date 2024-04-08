<?php

namespace Smartling\Models;

class IntStringPair {
    public function __construct(private int $key, private string $value)
    {
    }

    public function getKey(): int
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
