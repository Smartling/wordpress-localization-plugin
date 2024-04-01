<?php

namespace Smartling;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class Retry {
    public function __construct(public int $retryAttempts = 4, public int $retryTimeoutSeconds = 1)
    {
    }
}
