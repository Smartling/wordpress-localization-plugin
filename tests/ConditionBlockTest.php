<?php

use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;

class ConditionBlockTest extends PHPUnit_Framework_TestCase {
	public function testSimpleBlock () {
		$condition = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'foo', array ( 'bar' ) );

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition( $condition );

		$expectedResult = '( ' . $condition->__toString() . ' )';

		$actualResult = $block->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testTwoConditionBlockOperatorAnd () {
		$condition1 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'foo1', array ( 'bar1' ) );

		$condition2 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_LIKE, 'foo2', array ( '%bar2%' ) );

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition( $condition1 );
		$block->addCondition( $condition2 );

		$expectedResult = '( ' . $condition1->__toString() . ' AND ' . $condition2->__toString() . ' )';

		$actualResult = $block->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testTwoConditionBlockOperatorOr () {
		$condition1 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'foo1', array ( 'bar1' ) );

		$condition2 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_LIKE, 'foo2', array ( '%bar2%' ) );

		$block = ConditionBlock::getConditionBlock( ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR );
		$block->addCondition( $condition1 );
		$block->addCondition( $condition2 );

		$expectedResult = '( ' . $condition1->__toString() . ' OR ' . $condition2->__toString() . ' )';

		$actualResult = $block->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testConditionBlockOfTwoConditionBlocks () {
		$condition1 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'foo1', array ( 'bar1' ) );
		$condition2 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_LIKE, 'foo2', array ( '%bar2%' ) );
		$condition3 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_NOT_EQ, 'foo3', array ( 'bar3' ) );
		$condition4 = Condition::getCondition( ConditionBuilder::CONDITION_SIGN_LESS_OR_EQ, 'foo4', array ( 'bar4' ) );

		$block1 = ConditionBlock::getConditionBlock( ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR );
		$block1->addCondition( $condition1 );
		$block1->addCondition( $condition2 );

		$block2 = ConditionBlock::getConditionBlock( ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR );
		$block2->addCondition( $condition3 );
		$block2->addCondition( $condition4 );

		$block = ConditionBlock::getConditionBlock();
		$block->addConditionBlock( $block1 );
		$block->addConditionBlock( $block2 );


		$expectedResult = '( ' . $block1->__toString() . ' AND ' . $block2->__toString() . ' )';

		$actualResult = $block->__toString();

		$this->assertTrue( $actualResult === $expectedResult );
	}

}