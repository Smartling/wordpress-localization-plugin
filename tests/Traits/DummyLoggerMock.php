<?php

namespace Smartling\Tests\Traits;

use Smartling\Vendor\Psr\Log\LoggerInterface;
use Smartling\Vendor\Psr\Log\NullLogger;

/**
 * Class DummyLoggerMock
 * @package Smartling\Tests\Traits
 */
trait DummyLoggerMock
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!($this->logger instanceof LoggerInterface)) {
            $this->setLogger(new NullLogger());
        }

        return $this->logger;
    }


    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }


}