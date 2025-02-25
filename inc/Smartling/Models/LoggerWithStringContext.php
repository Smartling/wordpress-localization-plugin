<?php

namespace Smartling\Models;

use Smartling\Vendor\Psr\Log\LoggerInterface;

interface LoggerWithStringContext extends LoggerInterface
{
    public function alterContext(array $context): void;

    public function withStringContext(array $context, callable $callable): mixed;
}
