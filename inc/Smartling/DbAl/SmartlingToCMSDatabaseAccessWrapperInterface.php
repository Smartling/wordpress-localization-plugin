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
    public function fetch($query, $output = OBJECT);

    /**
     * Escape string value
     *
     * @param string $string
     *
     * @return mixed
     */
    function escape($string);

    public function completeTableName(string $tableName): string;

    /**
     * @param $tableName
     *
     * @return mixed
     */
    public function completeMultisiteTableName($tableName);

    /**
     * @return integer
     */
    function getLastInsertedId();

    /**
     * @return string
     */
    function getLastErrorMessage();
}