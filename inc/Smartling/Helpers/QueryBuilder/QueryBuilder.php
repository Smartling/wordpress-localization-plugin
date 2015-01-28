<?php

namespace Smartling\Helpers\QueryBuilder;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapper;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;

/**
 * Helps to build CRUD SQL queries
 * Class QueryBuilder
 *
 * @package Smartling\Helpers
 */
class QueryBuilder {
	/**
	 * Exception code for bad sort options
	 */
	const EXCEPTION_CODE_INVALID_SORT_OPTIONS = 1;

	/**
	 * Exception code for bad field list
	 */
	const EXCEPTION_CODE_INVALID_FIELD_LIST = 2;

	/**
	 * Exception code for empty field list
	 */
	const EXCEPTION_CODE_EMPTY_FIELD_LIST = 4;


	/**
	 * Validates sorting options
	 *
	 * @param array $fieldNames
	 * @param array $sortOptions
	 *
	 * @throws \InvalidArgumentException
	 * @return bool
	 */
	public static function validateSortOptions ( array $fieldNames, array $sortOptions = array () ) {
		if ( ! is_array( $sortOptions ) ) {
			throw new \InvalidArgumentException(
				'Sorting options MUST always be an array',
				self::EXCEPTION_CODE_INVALID_SORT_OPTIONS
			);
		}

		if ( ! is_array( $fieldNames ) ) {
			throw new \InvalidArgumentException(
				'Field name list MUST always be an array',
				self::EXCEPTION_CODE_INVALID_FIELD_LIST
			);
		}

		if ( ! is_array( $fieldNames ) ) {
			throw new \InvalidArgumentException(
				'Field list CANNOT be empty',
				self::EXCEPTION_CODE_EMPTY_FIELD_LIST
			);
		}

		$valid = true;

		$fieldValues = array (
			SmartlingToCMSDatabaseAccessWrapper::SORT_OPTION_ASC,
			SmartlingToCMSDatabaseAccessWrapper::SORT_OPTION_DESC
		);

		foreach ( $sortOptions as $field => $order ) {
			if ( ! in_array( $field, $fieldNames ) || ! in_array( $order, $fieldValues ) ) {
				$valid = false;
				break;
			}
		}

		return $valid;
	}

	/**
	 * @param $pageOptions
	 *
	 * @return bool true or false
	 */
	public static function validatePageOptions ( $pageOptions ) {
		$valid = null;

		switch ( true ) {

			// no sorting enabled
			case is_null( $pageOptions ) : {
				$valid = true;
				break;
			}

			// some sorting enabled
			case is_array( $pageOptions ) : {

				//array('limit' => 20, 'page' => 1)

				$validLimit = isset( $pageOptions['limit'] ) && 0 < (int) $pageOptions['limit'];

				$validPage = isset( $pageOptions['page'] ) && 0 < (int) $pageOptions['page'];

				$valid = $validLimit && $validPage;

				break;
			}

			// not null or array
			default : {
				$valid = false;
				break;
			}
		}

		return $valid;
	}

	protected static $sqlFunctionNames = array
	(
		'count',
	);

	/**
	 * @param string         $tableName
	 * @param array          $fieldsList
	 * @param ConditionBlock $conditions
	 * @param array          $sortOptions
	 * @param null|array     $pageOptions
	 *
	 * @return string
	 */
	public static function buildSelectQuery (
		$tableName,
		$fieldsList,
		ConditionBlock $conditions = null,
		$sortOptions,
		$pageOptions
	) {

		$fieldsString = self::buildFieldListString( $fieldsList );

		$query = vsprintf(
			"SELECT %s FROM %s",
			array (
				$fieldsString,
				self::escapeName( $tableName )
			)
		);

		if ( $conditions instanceof ConditionBlock ) {
			$query .= vsprintf( ' WHERE %s', array ( (string) $conditions ) );
		}

		$query .= self::buildSortSubQuery( $sortOptions );

		$query .= self::buildLimitSubQuery( $pageOptions );

		return $query;
	}

	/**
	 * @param                $tableName
	 * @param ConditionBlock $conditions
	 * @param null           $pageOptions
	 *
	 * @return string
	 */
	public static function buildDeleteQuery ( $tableName, ConditionBlock $conditions = null, $pageOptions = null ) {
		$query = vsprintf( 'DELETE FROM %s', array ( self::escapeName( $tableName ) ) );

		if ( $conditions instanceof ConditionBlock ) {
			$query .= vsprintf( ' WHERE %s', array ( (string) $conditions ) );
		}

		$query .= self::buildLimitSubQuery( $pageOptions, true );

		return $query;
	}

	/**
	 * @param                $tableName
	 * @param array          $fieldValueList
	 * @param ConditionBlock $conditions
	 * @param null           $pageOptions
	 *
	 * @return string
	 */
	public static function buildUpdateQuery (
		$tableName,
		array $fieldValueList,
		ConditionBlock $conditions = null,
		$pageOptions = null
	) {
		$template = 'UPDATE %s SET %s';

		$query = vsprintf( $template, array (
			self::escapeName( $tableName ),
			self::buildAssignmentSubQuery( $fieldValueList )
		) );

		if ( $conditions instanceof ConditionBlock ) {
			$query .= vsprintf( ' WHERE %s', array ( (string) $conditions ) );
		}

		if ( null !== $pageOptions ) {
			$query .= self::buildLimitSubQuery( $pageOptions, true );
		}

		return $query;
	}

	/**
	 * @param string $tableName
	 * @param array  $fieldValueList
	 *
	 * @return string
	 */
	public static function buildInsertQuery ( $tableName, array $fieldValueList ) {
		$template = 'INSERT INTO %s (%s) VALUES (%s)';

		$query = vsprintf( $template, array (
			self::escapeName( $tableName ),
			self::buildFieldListString( array_keys( $fieldValueList ) ),
			implode( ',', array_map( function ( $item ) {
				return "'{$item}'";
			}, self::escapeValues( array_values( $fieldValueList ) ) ) )
		) );

		return $query;
	}

	/**
	 * @param array $fieldList
	 *
	 * @return string
	 */
	public static function buildFieldListString ( array $fieldList ) {
		$prebuild = array ();

		foreach ( $fieldList as $field ) {
			if ( is_array( $field ) ) {
				$fld        = reset( $field );
				$alias      = end( $field );
				$prebuild[] = self::escapeName( $fld ) . ' AS ' . self::escapeName( $alias );
			} else {
				$prebuild[] = self::escapeName( $field );
			}
		}

		return implode( ', ', $prebuild );
	}

	/**
	 * @param $sortOptions
	 *
	 * @return string
	 */
	private static function buildSortSubQuery ( array  $sortOptions ) {
		$part = '';

		if ( ! empty( $sortOptions ) ) {
			$preOptions = array ();

			foreach ( $sortOptions as $filed => $value ) {
				$preOptions[] = vsprintf( "`%s` %s", array ( $filed, $value ) );
			}

			$part .= vsprintf( " ORDER BY %s", array ( implode( ' , ', $preOptions ) ) );
		}

		return $part;
	}

	/**
	 * @param array $pageOptions
	 * @param bool  $ignorePage (for update|delete purposes)
	 *
	 * @return string
	 */
	private static function buildLimitSubQuery ( $pageOptions, $ignorePage = false ) {
		$part = '';

		if ( null !== $pageOptions ) {
			$limit = (int) $pageOptions['limit'];
			if ( true === $ignorePage ) {
				$part .= vsprintf( ' LIMIT %d', array ( $limit ) );
			} else {
				$offset = ( ( (int) $pageOptions['page'] ) - 1 ) * $limit;
				$part .= vsprintf( ' LIMIT %d,%d', array ( $offset, $limit ) );
			}
		}

		return $part;
	}

	/**
	 * @param array $fieldValueList
	 *
	 * @return string
	 */
	private static function buildAssignmentSubQuery ( array $fieldValueList ) {
		$subQueryParts = array ();

		foreach ( $fieldValueList as $column => $value ) {
			$subQueryParts[] = vsprintf( '%s = %s',
				array ( self::escapeName( $column ), self::escapeValue( $value ) ) );
		}

		return implode( ', ', $subQueryParts );

	}

	/**
	 * Escapes a value to be safe for SQL
	 *
	 * @param $value
	 *
	 * @return string
	 */
	public static function escapeValue ( $value ) {
		return addslashes( trim( $value ) );
	}

	/**
	 * Escapes an array of values to be safe for SQL
	 *
	 * @param array $values
	 *
	 * @return array
	 */
	public static function escapeValues ( array $values ) {
		$result = array ();

		foreach ( $values as $value ) {
			$result[] = self::escapeValue( $value );
		}

		return $result;
	}

	/**
	 * Escapes field name
	 *
	 * @param $fieldName
	 *
	 * @return string
	 */
	public static function escapeName ( $fieldName ) {
		$tmp = strtolower( trim( $fieldName ) );

		$is_function = false;

		foreach ( self::$sqlFunctionNames as $functionName ) {
			$pos = strpos( $tmp, $functionName . '(' );

			if ( false !== $pos && 0 == $pos ) {
				$is_function = true;
				break;
			}
		}

		return $is_function ? $fieldName : "`{$fieldName}`";
	}
}