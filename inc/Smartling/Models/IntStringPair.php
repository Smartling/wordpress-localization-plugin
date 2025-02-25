<?php

namespace Smartling\Models;

readonly class IntStringPair
{
    public function __construct(public int $key, public string $value)
    {
    }
}
