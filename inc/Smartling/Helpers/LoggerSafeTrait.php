<?php

namespace Smartling\Helpers;

use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Vendor\Psr\Log\LoggerInterface;
use Smartling\Vendor\Psr\Log\NullLogger;

trait LoggerSafeTrait {
    private ?LoggerInterface $logger = null;

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
