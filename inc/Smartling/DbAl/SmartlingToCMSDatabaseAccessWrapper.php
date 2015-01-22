<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

interface SmartlingToCMSDatabaseAccessWrapper
{

    const SORT_OPTION_ASC   = 'ASC';
    const SORT_OPTION_DESC  = 'DESC';

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

    /**
     * Fetches data from database
     * @param $query
     * @return mixed
     */
    function fetch($query);

    /**
     * Escape string value
     * @param string $string
     * @return mixed
     */
    function escape($string);

    /**
     * @param string $tableName
     * @return mixed
     */
    function completeTableName($tableName);
}