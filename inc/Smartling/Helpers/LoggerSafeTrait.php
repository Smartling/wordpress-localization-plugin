<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Smartling\MonologWrapper\MonologWrapper;

trait LoggerSafeTrait {
    private $logger;

    public function getLogger(): LoggerInterface {
        if ($this->logger === null) {
            $this->logger = MonologWrapper::getLogger(static::class);
        }
        if (!$this->logger instanceof LoggerInterface) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }
}
