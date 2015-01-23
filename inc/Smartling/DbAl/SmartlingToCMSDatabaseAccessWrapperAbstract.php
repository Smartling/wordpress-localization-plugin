<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

/**
 * Class SmartlingToCMSDatabaseAccessWrapperAbstract
 * @package Smartling\DbAl
 */
abstract class SmartlingToCMSDatabaseAccessWrapperAbstract implements SmartlingToCMSDatabaseAccessWrapper
{
    /**
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}