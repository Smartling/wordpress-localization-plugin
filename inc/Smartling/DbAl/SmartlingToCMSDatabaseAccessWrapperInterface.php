<?php

namespace Smartling\DbAl;

/**
 * Interface SmartlingToCMSDatabaseAccessWrapperInterface
 *
 * @package Smartling\DbAl
 */
interface SmartlingToCMSDatabaseAccessWrapperInterface
{

    /**
     * the look of acs sort direction
     */
    const SORT_OPTION_ASC = 'ASC';

    /**
     * the look of desc sort direction
     */
    const SORT_OPTION_DESC = 'DESC';

    /**
     * Executes SQL query and returns the result
     *
     * @param $query
     *
     * @return mixed
     */
    function query($query);

    /**
     * Fetches data from database
     *
     * @param string $query
     * @param string $output \OBJECT || \ARRAY_A
     *
     * @return mixed
     */
    function fetch($query, $output);

    /**
     * Escape string value
     *
     * @param string $string
     *
     * @return mixed
     */
    function escape($string);

    /**
     * @param string $tableName
     *
     * @return mixed
     */
    function completeTableName($tableName);

    /**
     * @return integer
     */
    function getLastInsertedId();

    /**
     * @return string
     */
    function getLastErrorMessage();
}