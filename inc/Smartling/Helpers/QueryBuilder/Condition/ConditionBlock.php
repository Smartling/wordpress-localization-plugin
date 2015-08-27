<?php

namespace Smartling\Helpers\QueryBuilder\Condition;

/**
 * Class ConditionBlock
 *
 * @package Smartling\Helpers\QueryBuilder\Condition
 */
class ConditionBlock {

	/**
	 * @var array of Condition
	 */
	private $conditions = [ ];

	/**
	 * @var array of ConditionBlock
	 */
	private $blocks = [ ];

	/**
	 * @var string
	 */
	private $operator;

	/**
	 * @param $conditionOperator
	 *
	 * @return ConditionBlock
	 */
	public static function getConditionBlock (
		$conditionOperator = ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND
	) {
		return new self( $conditionOperator );
	}

	/**
	 * Constructor
	 *
	 * @param $conditionOperator
	 */
	public function __construct ( $conditionOperator ) {
		if ( ! $this->validateOperator( $conditionOperator ) ) {
			throw new \InvalidArgumentException( 'Invalid operator' );
		}

		$this->operator = vsprintf( ' %s ', [ $conditionOperator ] );
	}

	/**
	 * @param $operator
	 *
	 * @return bool
	 */
	private function validateOperator ( $operator ) {
		$validOperators = [
			ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR,
			ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND,
		];

		return in_array( $operator, $validOperators );
	}

	/**
	 * Adds condition in block
	 *
	 * @param Condition $condition
	 */
	public function addCondition ( Condition $condition ) {
		$this->conditions[] = $condition;
	}

	/**
	 * Adds conditionBlock in block
	 *
	 * @param ConditionBlock $block
	 */
	public function addConditionBlock ( ConditionBlock $block ) {
		$this->blocks[] = $block;
	}

	/**
	 * renders block
	 *
	 * @return string
	 */
	public function __toString () {
		$preRendered = [ ];

		foreach ( $this->conditions as $condition ) {
			$preRendered[] = (string) $condition;
		}

		foreach ( $this->blocks as $block ) {
			$preRendered[] = (string) $block;
		}

		return vsprintf( '( %s )', [ implode( $this->operator, $preRendered ) ] );
	}
}