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
	private $conditions = array ();

	/**
	 * @var array of ConditionBlock
	 */
	private $blocks = array ();

	/**
	 * @var string
	 */
	private $operator;

	/**
	 * @param $conditionOperator
	 *
	 * @return ConditionBlock
	 */
	public static function getConditionBlock ( $conditionOperator = ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND) {
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

		$this->operator = " {$conditionOperator} ";
	}

	/**
	 * @param $operator
	 *
	 * @return bool
	 */
	private function validateOperator ( $operator ) {
		$validOperators = array (
			ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR,
			ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND
		);

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
		$preRendered = array ();

		foreach ( $this->conditions as $condition ) {
			$preRendered[] = (string) $condition;
		}

		foreach ( $this->blocks as $block ) {
			$preRendered[] = (string) $block;
		}

		return vsprintf( '( %s )', array ( implode( $this->operator, $preRendered ) ) );
	}
}