<?php

namespace Smartling\Tests\Smartling\Submissions;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Submissions\SubmissionManager;

class SubmissionManagerTest extends TestCase
{
    private $db;
    private \stdClass $result;
    private $subject;
    public function setUp(): void
    {
        parent::setUp();
        defined('OBJECT') || define('OBJECT', 'OBJECT');
        $this->db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $this->db->method('completeTableName')->willReturn('wp_posts');
        $this->result = new \stdClass();
        $this->result->cnt = 0;
        $this->subject = $this->createPartialMock(SubmissionManager::class, ['fetchData', 'getDbal', 'getLogger']);
        $this->subject->expects($this->once())->method('fetchData')->willReturn([]);
        $this->subject->method('getLogger')->willReturn(new NullLogger());
    }

    public function testSearch_EmptyConditionBlock()
    {
        $db = clone $this->db;
        $db->expects($this->once())->method('fetch')->with('SELECT COUNT(*) AS `cnt` FROM `wp_posts`')->willReturn([$this->result]);

        $x = clone $this->subject;
        $x->method('getDbal')->willReturn($db);

        $x->searchByCondition(ConditionBlock::getConditionBlock());
    }

    public function testSearch_ConditionBlockWithConditions()
    {
        $db = clone $this->db;
        $db->expects($this->once())->method('fetch')->with('SELECT COUNT(*) AS `cnt` FROM `wp_posts` WHERE ( ( `post_status` = \'draft\' ) )')->willReturn([$this->result]);

        $x = clone $this->subject;
        $x->method('getDbal')->willReturn($db);

        $block = ConditionBlock::getConditionBlock();
        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'post_status', ['draft']));
        $x->searchByCondition($block);
    }

    public function testSearch_ConditionBlockWithBlocks()
    {
        $db = clone $this->db;
        $db->expects($this->once())->method('fetch')->with('SELECT COUNT(*) AS `cnt` FROM `wp_posts` WHERE ( ( ( `post_status` = \'draft\' ) ) )')->willReturn([$this->result]);

        $x = clone $this->subject;
        $x->method('getDbal')->willReturn($db);

        $conditionBlock = ConditionBlock::getConditionBlock();
        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'post_status', ['draft']));
        $block = ConditionBlock::getConditionBlock();
        $block->addConditionBlock($conditionBlock);
        $x->searchByCondition($block);
    }

    public function testSearch_ConditionBlockWithBlocksAndConditions()
    {
        $db = clone $this->db;
        $db->expects($this->once())->method('fetch')->with('SELECT COUNT(*) AS `cnt` FROM `wp_posts` WHERE ( ( `guid` = \'\' AND ( `post_status` = \'draft\' ) ) )')->willReturn([$this->result]);

        $x = clone $this->subject;
        $x->method('getDbal')->willReturn($db);

        $conditionBlock = ConditionBlock::getConditionBlock();
        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'post_status', ['draft']));
        $block = ConditionBlock::getConditionBlock();
        $block->addConditionBlock($conditionBlock);
        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'guid', ['']));
        $x->searchByCondition($block);
    }
}
