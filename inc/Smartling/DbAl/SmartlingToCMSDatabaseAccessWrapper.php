<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

interface SmartlingToCMSDatabaseAccessWrapper
{
    /**
     * Constructor
     * @param LoggerInterface $logger
     */
    function __construct(LoggerInterface $logger);

    /**
     * Executes SQL query and returns the result
     * @param $query
     * @return mixed
     */
    function query($query);
}