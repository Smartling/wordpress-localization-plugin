<?php

namespace Smartling\Helpers\QueryBuilder\Condition;

use Smartling\Helpers\QueryBuilder\QueryBuilder;

/**
 * Class ConditionBuilder
 *
 * @package Smartling\Helpers\QueryBuilder\Condition
 */
class ConditionBuilder {
	/**
	 * const for '='
	 */
	const CONDITION_SIGN_EQ = '%s = \'%s\'';

	/**
	 * const for '<>'
	 */
	const CONDITION_SIGN_NOT_EQ = '%s <> \'%s\'';

	/**
	 * const for '>'
	 */
	const CONDITION_SIGN_MORE = '%s > \'%s\'';

	/**
	 * const for '>='
	 */
	const CONDITION_SIGN_MORE_OR_EQ = '%s >= \'%s\'';

	/**
	 * const for '<'
	 */
	const CONDITION_SIGN_LESS = '%s < \'%s\'';

	/**
	 * const for '<='
	 */
	const CONDITION_SIGN_LESS_OR_EQ = '%s <= \'%s\'';

	/**
	 * const for 'BETWEEN
	 */
	const CONDITION_SIGN_BETWEEN = '%s BETWEEN \'%s\' AND \'%s\'';

	/**
	 * const for 'LIKE'
	 */
	const CONDITION_SIGN_LIKE = '%s LIKE \'%s\'';

	/**
	 * const for 'IN'
	 */
	const CONDITION_SIGN_IN = '%s IN(%s)';

	/**
	 * const for 'NOT IN'
	 */
	const CONDITION_SIGN_NOT_IN = '%s NOT IN(%s)';

	/**
	 * const for 'AND'
	 */
	const CONDITION_BLOCK_LEVEL_OPERATOR_AND = 'AND';

	/**
	 * const for 'OR'
	 */
	const CONDITION_BLOCK_LEVEL_OPERATOR_OR = 'OR';

	/**
	 * @param $condition
	 * @param $parameters
	 *
	 * @return string
	 */
	public static function buildBlock ( $condition, $parameters ) {
		if ( ! self::validate( $condition, $parameters ) ) {
			throw new \InvalidArgumentException( 'Invalid condition or parameters' );
		}

		if ( in_array( $condition, array ( self::CONDITION_SIGN_IN, self::CONDITION_SIGN_NOT_IN ) ) ) {
			foreach ( $parameters as $index => & $param ) {
				if ( $index > 0 ) {
					$param = vsprintf( '\'%s\'', array ( QueryBuilder::escapeValue( $param ) ) );
				}
			}

			$field = reset( $parameters );
			unset( $parameters[0] );
			$values = implode( ', ', $parameters );

			$parameters = array ( $field, $values );
		}

		return vsprintf( $condition, $parameters );
	}

	/**
	 * Validates given parameters for building the block
	 *
	 * @param $condition
	 * @param $parameters
	 *
	 * @return bool
	 */
	private static function validate ( $condition, $parameters ) {
		return
			self::validateCondition( $condition )
			&& self::validateParametersCount( $condition, $parameters );
	}

	/**
	 * Validates parameters count
	 *
	 * @param string $condition
	 * @param array  $parameters
	 *
	 * @return bool
	 */
	private static function validateParametersCount ( $condition, $parameters ) {
		return count( $parameters ) === preg_match_all( '|(%s)|ius', $condition );
	}

	/**
	 * Validates $condition
	 *
	 * @param string $condition
	 *
	 * @return bool
	 */
	private static function validateCondition ( $condition ) {
		$conditions = array (
			self::CONDITION_SIGN_EQ,
			self::CONDITION_SIGN_NOT_EQ,
			self::CONDITION_SIGN_MORE,
			self::CONDITION_SIGN_MORE_OR_EQ,
			self::CONDITION_SIGN_LESS,
			self::CONDITION_SIGN_LESS_OR_EQ,
			self::CONDITION_SIGN_BETWEEN,
			self::CONDITION_SIGN_LIKE,
			self::CONDITION_SIGN_IN,
			self::CONDITION_SIGN_NOT_IN,
		);

		return in_array( $condition, $conditions );
	}
}