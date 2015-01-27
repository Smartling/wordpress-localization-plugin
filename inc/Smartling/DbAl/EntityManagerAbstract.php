<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

abstract class EntityManagerAbstract
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SmartlingToCMSDatabaseAccessWrapper
     */
    protected $dbal;

    /**
     * Constructor
     *
     * @param LoggerInterface                     $logger
     * @param SmartlingToCMSDatabaseAccessWrapper $dbal
     */
    public function __construct (LoggerInterface $logger, SmartlingToCMSDatabaseAccessWrapper $dbal)
    {
        $this->logger = $logger;
        $this->dbal = $dbal;
    }


}