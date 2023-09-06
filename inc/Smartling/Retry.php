<?php

namespace Smartling;

use Attribute;

#[Attribute]
class Retry {
    public function __construct(public int $retryAttempts = 4, public int $retryTimeoutSeconds = 1)
    {
    }
}
