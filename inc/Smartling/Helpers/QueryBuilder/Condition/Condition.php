<?php

namespace Smartling\Helpers\QueryBuilder\Condition;

use Smartling\Helpers\QueryBuilder\QueryBuilder;

/**
 * Class Condition
 *
 * @package Smartling\Helpers\QueryBuilder\Condition
 */
class Condition {

	/**
	 * @param string $condition
	 * @param string $field
	 * @param array  $values
	 *
	 * @return Condition
	 */
	public static function getCondition ( $condition, $field, array $values ) {
		return new self( $condition, $field, $values );
	}

	/**
	 * @var string
	 */
	private $condition;

	/**
	 * @var string
	 */
	private $field;

	/**
	 * @var array
	 */
	private $values;

	/**
	 * Constructor
	 *
	 * @param string $condition
	 * @param string $field
	 * @param array  $values
	 */
	protected function __construct ( $condition, $field, array $values ) {
		$this->condition = $condition;
		$this->field     = QueryBuilder::escapeName( $field );
		$this->values    = QueryBuilder::escapeValues( $values );
	}

	/**
	 * @return string
	 */
	public function __toString () {
		return ConditionBuilder::buildBlock( $this->condition, array_merge( array ( $this->field ), $this->values ) );
	}
}