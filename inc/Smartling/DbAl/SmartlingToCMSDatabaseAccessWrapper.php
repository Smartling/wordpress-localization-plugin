<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

/**
 * Interface SmartlingToCMSDatabaseAccessWrapper
 *
 * @package Smartling\DbAl
 */
interface SmartlingToCMSDatabaseAccessWrapper {

	/**
	 * the look of acs sort direction
	 */
	const SORT_OPTION_ASC = 'ASC';

	/**
	 * the look of desc sort direction
	 */
	const SORT_OPTION_DESC = 'DESC';

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger
	 */
	function __construct ( LoggerInterface $logger );

	/**
	 * Executes SQL query and returns the result
	 *
	 * @param $query
	 *
	 * @return mixed
	 */
	function query ( $query );

	/**
	 * Fetches data from database
	 *
	 * @param $query
	 *
	 * @return mixed
	 */
	function fetch ( $query );

	/**
	 * Escape string value
	 *
	 * @param string $string
	 *
	 * @return mixed
	 */
	function escape ( $string );

	/**
	 * @param string $tableName
	 *
	 * @return mixed
	 */
	function completeTableName ( $tableName );

	/**
	 * @return integer
	 */
	function getLastInsertedId();
}