<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

abstract class SmartlingToCMSDatabaseAccessWrapperAbstract implements SmartlingToCMSDatabaseAccessWrapper
{

    protected $logger = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


}