<?php
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;

/**
 * Class ConditionTest
 */
class ConditionTest extends PHPUnit_Framework_TestCase {

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testConditionValidation () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_EQ;

		$field = 'foo';

		Condition::getCondition( $conditionType, $field, array () )->__toString();
	}

	public function testEqCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_EQ;

		$field = 'foo';

		$value = 'bar';

		$expectedResult = "`{$field}` = '{$value}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testAutomaticValueEscapingSlash () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_EQ;

		$field = 'foo';

		$value = 'b\\ar';

		$expectedValue = 'b\\\\ar';

		$expectedResult = "`{$field}` = '{$expectedValue}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testAutomaticValueEscapingSingleQuote () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_EQ;

		$field = 'foo';

		$value = 'b\'ar';

		$expectedValue = 'b\\\'ar';

		$expectedResult = "`{$field}` = '{$expectedValue}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testAutomaticValueEscapingDoubleQuote () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_EQ;

		$field = 'foo';

		$value = 'b"ar';

		$expectedValue = 'b\\"ar';

		$expectedResult = "`{$field}` = '{$expectedValue}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testMoreCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_MORE;

		$field = 'foo';

		$value = 'bar';

		$expectedResult = "`{$field}` > '{$value}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testMoreOrEqCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_MORE_OR_EQ;

		$field = 'foo';

		$value = 'bar';

		$expectedResult = "`{$field}` >= '{$value}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testLessCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_LESS;

		$field = 'foo';

		$value = 'bar';

		$expectedResult = "`{$field}` < '{$value}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testLessOrEqCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_LESS_OR_EQ;

		$field = 'foo';

		$value = 'bar';

		$expectedResult = "`{$field}` <= '{$value}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testNotEqCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_NOT_EQ;

		$field = 'foo';

		$value = 'bar';

		$expectedResult = "`{$field}` <> '{$value}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testLikeCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_LIKE;

		$field = 'foo';

		$value = 'bar';

		$expectedResult = "`{$field}` LIKE '{$value}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testBetweenCondition () {
		$conditionType = ConditionBuilder::CONDITION_SIGN_BETWEEN;

		$field = 'foo';

		$value = 'bar';

		$anotherValue = 'sar';

		$expectedResult = "`{$field}` BETWEEN '{$value}' AND '{$anotherValue}'";

		$condition    = Condition::getCondition( $conditionType, $field, array ( $value, $anotherValue ) );
		$actualResult = $condition->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}


}