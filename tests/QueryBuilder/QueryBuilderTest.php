<?php
namespace Smartling\Tests\QueryBuilder;

use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;

/**
 * Class QueryBuilderTest
 *
 */
class QueryBuilderTest extends \PHPUnit_Framework_TestCase
{

	public function testFieldNameEscapingSimple()
	{
		$field = 'foo';

		$expectedResult = '`foo`';

		$actualResult = QueryBuilder::escapeName($field);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testFieldNameEscapingFunctionName()
	{
		$field = 'count(*)';

		$expectedResult = 'count(*)';

		$actualResult = QueryBuilder::escapeName($field);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testFieldListBuilderWithOneField()
	{
		$fields = ['foo'];

		$expectedResult = '`foo`';

		$actualResult = QueryBuilder::buildFieldListString($fields);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testFieldListBuilderWithTwoField()
	{
		$fields = ['foo', 'bar'];

		$expectedResult = '`foo`, `bar`';

		$actualResult = QueryBuilder::buildFieldListString($fields);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testFieldListBuilderWithAlias()
	{
		$fields = [['foo' => 'bar']];

		$expectedResult = '`foo` AS `bar`';

		$actualResult = QueryBuilder::buildFieldListString($fields);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testFieldListBuilderComplex()
	{
		$fields = ['id', ['foo' => 'bar'], ['bar' => 'foo'], 'status'];

		$expectedResult = '`id`, `foo` AS `bar`, `bar` AS `foo`, `status`';

		$actualResult = QueryBuilder::buildFieldListString($fields);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testSelectSimple()
	{
		$table = 'fooTable';

		$fields = ['id', ['foo', 'bar'], ['bar', 'foo'], 'status'];

		$expectedResult = 'SELECT ' . QueryBuilder::buildFieldListString($fields) . " FROM `{$table}`";

		$actualResult = QueryBuilder::buildSelectQuery($table, $fields, null, [], null);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testSelectSimpleWithGroupBy()
	{
		$table = 'fooTable';

		$fields = ['id', ['foo', 'bar'], ['bar', 'foo'], 'status'];

		$expectedResult = 'SELECT ' . QueryBuilder::buildFieldListString($fields) . " FROM `{$table}` GROUP BY `status`";

		$actualResult = QueryBuilder::buildSelectQuery($table, $fields, null, [], null, ['status']);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testSelectWithLimit()
	{
		$table = 'fooTable';

		$fields = ['id', ['foo', 'bar'], ['bar', 'foo'], 'status'];

		$limitOptions = ['limit' => 3, 'page' => 4];

		$offset = ($limitOptions['page'] - 1) * $limitOptions['limit'];

		$expectedResult = QueryBuilder::buildSelectQuery($table, $fields, null, [],
														 null) . " LIMIT {$offset},{$limitOptions['limit']}";

		$actualResult = QueryBuilder::buildSelectQuery($table, $fields, null, [], $limitOptions);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testSelectWithCondition()
	{
		$table = 'fooTable';

		$fields = ['id', ['foo', 'bar'], ['bar', 'foo'], 'status'];

		$limitOptions = ['limit' => 3, 'page' => 4];

		$offset = ($limitOptions['page'] - 1) * $limitOptions['limit'];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [5]));

		$stringCondition = $block->__toString();

		$pagination = " LIMIT {$offset},{$limitOptions['limit']}";

		$expectedResult = QueryBuilder::buildSelectQuery($table, $fields, null, [],
														 null) . ' WHERE ' . $stringCondition . $pagination;

		$actualResult = QueryBuilder::buildSelectQuery($table, $fields, $block, [], $limitOptions);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testSelectWithSorting()
	{
		$table = 'fooTable';

		$fields = ['id', ['foo', 'bar'], ['bar', 'foo'], 'status'];

		$limitOptions = ['limit' => 3, 'page' => 4];

		$sorting = ['id' => 'ASC'];

		$offset = ($limitOptions['page'] - 1) * $limitOptions['limit'];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [5]));

		$stringCondition = $block->__toString();

		$pagination = " LIMIT {$offset},{$limitOptions['limit']}";

		$sortingString = ' ORDER BY `id` ASC';

		$expectedResult = QueryBuilder::buildSelectQuery($table, $fields, null, [],
														 null) . ' WHERE ' . $stringCondition . $sortingString . $pagination;

		$actualResult = QueryBuilder::buildSelectQuery($table, $fields, $block, $sorting, $limitOptions);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testSelectWithComplexSorting()
	{
		$table = 'fooTable';

		$fields = ['id', ['foo', 'bar'], ['bar', 'foo'], 'status'];

		$limitOptions = ['limit' => 3, 'page' => 4];

		$sorting = ['id' => 'ASC', 'status' => 'DESC'];

		$offset = ($limitOptions['page'] - 1) * $limitOptions['limit'];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [5]));

		$stringCondition = $block->__toString();

		$pagination = " LIMIT {$offset},{$limitOptions['limit']}";

		$sortingString = ' ORDER BY `id` ASC , `status` DESC';

		$expectedResult = QueryBuilder::buildSelectQuery($table, $fields, null, [],
														 null) . ' WHERE ' . $stringCondition . $sortingString . $pagination;

		$actualResult = QueryBuilder::buildSelectQuery($table, $fields, $block, $sorting, $limitOptions);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testInsertSimple()
	{
		$table = 'fooTable';

		$fields = ['id' => 5];

		$expectedResult = "INSERT  INTO `{$table}` (" . QueryBuilder::buildFieldListString(array_keys($fields)) . ") VALUES ('5')";

		$actualResult = QueryBuilder::buildInsertQuery($table, $fields);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testInsertIgnoreSimple()
	{
		$table = 'fooTable';

		$fields = ['id' => 5];

		$expectedResult = "INSERT IGNORE INTO `{$table}` (" . QueryBuilder::buildFieldListString(array_keys($fields)) . ") VALUES ('5')";

		$actualResult = QueryBuilder::buildInsertQuery($table, $fields, true);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testDeleteSimple()
	{
		$table = 'fooTable';

		$expectedResult = "DELETE FROM " . QueryBuilder::escapeName($table);

		$actualResult = QueryBuilder::buildDeleteQuery($table);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testDeleteWithCondition()
	{
		$table = 'fooTable';

		$condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [5]);

		$block = ConditionBlock::getConditionBlock();

		$block->addCondition($condition);

		$expectedResult = "DELETE FROM " . QueryBuilder::escapeName($table) . ' WHERE ' . $block->__toString();

		$actualResult = QueryBuilder::buildDeleteQuery($table, $block);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testDeleteWithConditionAndLimit()
	{
		$table = 'fooTable';

		$condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [5]);

		$pageOptions = ['page' => 100, 'limit' => 1];

		$block = ConditionBlock::getConditionBlock();

		$block->addCondition($condition);

		$expectedResult = "DELETE FROM " . QueryBuilder::escapeName($table) . ' WHERE ' . $block->__toString() . ' LIMIT ' . $pageOptions['limit'];

		$actualResult = QueryBuilder::buildDeleteQuery($table, $block, $pageOptions);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testSimpleFieldValuePairAssignment()
	{
		$fieldValuePairs = ['a' => 5];

		$expectedResult = QueryBuilder::escapeName('a') . ' = ' . '\'' . QueryBuilder::escapeValue(5) . '\'';
		$actualResult = QueryBuilder::buildAssignmentSubQuery($fieldValuePairs);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testUpdateSimple()
	{
		$fieldValuePairs = ['a' => 5];

		$table = 'fooTable';

		$expectedResult = "UPDATE " . QueryBuilder::escapeName($table) . ' SET ' . QueryBuilder::buildAssignmentSubQuery($fieldValuePairs);

		$actualResult = QueryBuilder::buildUpdateQuery($table, $fieldValuePairs);

		self::assertTrue($actualResult === $expectedResult);
	}

	public function testUpdateWithCondition()
	{
		$fieldValuePairs = ['a' => 5];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'a', [5]));

		$table = 'fooTable';

		$expectedResult = QueryBuilder::buildUpdateQuery($table, $fieldValuePairs) . ' WHERE ' . $block->__toString();

		$actualResult = QueryBuilder::buildUpdateQuery($table, $fieldValuePairs, $block);

		self::assertTrue($actualResult === $expectedResult);

	}

	public function testUpdateWithConditionAndLimit()
	{
		$fieldValuePairs = ['a' => 5];

		$pageOptions = ['page' => 100, 'limit' => 1];

		$block = ConditionBlock::getConditionBlock();
		$block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'a', [5]));

		$table = 'fooTable';

		$expectedResult = QueryBuilder::buildUpdateQuery($table, $fieldValuePairs,
														 $block) . ' LIMIT ' . $pageOptions['limit'];

		$actualResult = QueryBuilder::buildUpdateQuery($table, $fieldValuePairs, $block, $pageOptions);

		self::assertTrue($actualResult === $expectedResult);
	}

}