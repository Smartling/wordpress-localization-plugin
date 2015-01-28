<?php
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;

/**
 * Class QueryBuilderTest
 *
 */
class QueryBuilderTest extends PHPUnit_Framework_TestCase {

	public function testFieldNameEscapingSimple () {
		$field = 'foo';

		$expectedResult = '`foo`';

		$actualResult = QueryBuilder::escapeName( $field );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testFieldNameEscapingFunctionName () {
		$field = 'count(*)';

		$expectedResult = 'count(*)';

		$actualResult = QueryBuilder::escapeName( $field );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testFieldListBuilderWithOneField () {
		$fields = array (
			'foo'
		);

		$expectedResult = '`foo`';

		$actualResult = QueryBuilder::buildFieldListString( $fields );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testFieldListBuilderWithTwoField () {
		$fields = array (
			'foo',
			'bar'
		);

		$expectedResult = '`foo`, `bar`';

		$actualResult = QueryBuilder::buildFieldListString( $fields );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testFieldListBuilderWithAlias () {
		$fields = array (
			array ( 'foo', 'bar' )
		);

		$expectedResult = '`foo` AS `bar`';

		$actualResult = QueryBuilder::buildFieldListString( $fields );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testFieldListBuilderComplex () {
		$fields = array (
			'id',
			array ( 'foo', 'bar' ),
			array ( 'bar', 'foo' ),
			'status'
		);

		$expectedResult = '`id`, `foo` AS `bar`, `bar` AS `foo`, `status`';

		$actualResult = QueryBuilder::buildFieldListString( $fields );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testSelectSimple () {
		$table = 'fooTable';

		$fields = array (
			'id',
			array ( 'foo', 'bar' ),
			array ( 'bar', 'foo' ),
			'status'
		);

		$expectedResult = 'SELECT ' . QueryBuilder::buildFieldListString( $fields ) . " FROM `{$table}`";

		$actualResult = QueryBuilder::buildSelectQuery( $table, $fields, null, array (), null );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testSelectWithLimit () {
		$table = 'fooTable';

		$fields = array (
			'id',
			array ( 'foo', 'bar' ),
			array ( 'bar', 'foo' ),
			'status'
		);

		$limitOptions = array (
			'limit' => 3,
			'page'  => 4
		);

		$offset = ( $limitOptions['page'] - 1 ) * $limitOptions['limit'];

		$expectedResult = QueryBuilder::buildSelectQuery( $table, $fields, null, array (),
				null ) . " LIMIT {$offset},{$limitOptions['limit']}";

		$actualResult = QueryBuilder::buildSelectQuery( $table, $fields, null, array (), $limitOptions );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testSelectWithCondition () {
		$table = 'fooTable';

		$fields = array (
			'id',
			array ( 'foo', 'bar' ),
			array ( 'bar', 'foo' ),
			'status'
		);

		$limitOptions = array (
			'limit' => 3,
			'page'  => 4
		);

		$offset = ( $limitOptions['page'] - 1 ) * $limitOptions['limit'];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition( Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'id', array ( 5 ) ) );

		$stringCondition = $block->__toString();

		$pagination = " LIMIT {$offset},{$limitOptions['limit']}";

		$expectedResult = QueryBuilder::buildSelectQuery( $table, $fields, null, array (),
				null ) . ' WHERE ' . $stringCondition . $pagination;

		$actualResult = QueryBuilder::buildSelectQuery( $table, $fields, $block, array (), $limitOptions );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testSelectWithSorting () {
		$table = 'fooTable';

		$fields = array (
			'id',
			array ( 'foo', 'bar' ),
			array ( 'bar', 'foo' ),
			'status'
		);

		$limitOptions = array (
			'limit' => 3,
			'page'  => 4
		);

		$sorting = array(
			'id' => 'ASC'
		);

		$offset = ( $limitOptions['page'] - 1 ) * $limitOptions['limit'];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition( Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'id', array ( 5 ) ) );

		$stringCondition = $block->__toString();

		$pagination = " LIMIT {$offset},{$limitOptions['limit']}";

		$sortingString = ' ORDER BY `id` ASC';

		$expectedResult = QueryBuilder::buildSelectQuery( $table, $fields, null, array (),
				null ) . ' WHERE ' . $stringCondition . $sortingString . $pagination;

		$actualResult = QueryBuilder::buildSelectQuery( $table, $fields, $block, $sorting, $limitOptions );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testSelectWithComplexSorting () {
		$table = 'fooTable';

		$fields = array (
			'id',
			array ( 'foo', 'bar' ),
			array ( 'bar', 'foo' ),
			'status'
		);

		$limitOptions = array (
			'limit' => 3,
			'page'  => 4
		);

		$sorting = array(
			'id' => 'ASC',
			'status' => 'DESC'
		);

		$offset = ( $limitOptions['page'] - 1 ) * $limitOptions['limit'];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition( Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'id', array ( 5 ) ) );

		$stringCondition = $block->__toString();

		$pagination = " LIMIT {$offset},{$limitOptions['limit']}";

		$sortingString = ' ORDER BY `id` ASC , `status` DESC';

		$expectedResult = QueryBuilder::buildSelectQuery( $table, $fields, null, array (),
				null ) . ' WHERE ' . $stringCondition . $sortingString . $pagination;

		$actualResult = QueryBuilder::buildSelectQuery( $table, $fields, $block, $sorting, $limitOptions );

		$this->assertTrue( $actualResult === $expectedResult );
	}

	public function testInsertSimple()
	{
		$table = 'fooTable';

		$fields = array (
			'id' => 5
		);

		$expectedResult = "INSERT INTO `{$table}` (" . QueryBuilder::buildFieldListString(array_keys($fields)) . ") VALUES ('5')";

		$actualResult = QueryBuilder::buildInsertQuery($table, $fields);

		$this->assertTrue( $actualResult === $expectedResult );
	}

}