<?php

namespace Smartling\Tests\Smartling\Submissions;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\JobManager;
use Smartling\Jobs\SubmissionsJobsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class SubmissionManagerTest extends TestCase
{
    private $db;
    private \stdClass $result;
    private $subject;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        defined('OBJECT') || define('OBJECT', 'OBJECT');
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $this->db->method('completeTableName')->willReturnCallback(function ($tableName) {return 'wp_' . $tableName;});
        $this->result = new \stdClass();
        $this->result->cnt = 0;
        $this->subject = $this->createPartialMock(SubmissionManager::class, ['fetchData', 'getDbal', 'getLogger']);
        $this->subject->method('getLogger')->willReturn(new NullLogger());
    }

    public function testSearch_EmptyConditionBlock()
    {
        $db = $this->db;
        $db->expects($this->once())->method('fetch')->with("SELECT COUNT(DISTINCT s.id) AS cnt\n     FROM wp_smartling_submissions AS s\n        LEFT JOIN wp_smartling_submissions_jobs AS sj ON s.id = sj.submission_id\n        LEFT JOIN wp_smartling_jobs AS j ON sj.job_id = j.id ")->willReturn([$this->result]);

        $x = $this->subject;
        $x->expects($this->once())->method('fetchData')->willReturn([]);
        $x->method('getDbal')->willReturn($db);

        $x->searchByCondition(ConditionBlock::getConditionBlock());
    }

    public function testSearch_ConditionBlockWithConditions()
    {
        $db = $this->db;
        $db->expects($this->once())->method('fetch')->with("SELECT COUNT(DISTINCT s.id) AS cnt\n     FROM wp_smartling_submissions AS s\n        LEFT JOIN wp_smartling_submissions_jobs AS sj ON s.id = sj.submission_id\n        LEFT JOIN wp_smartling_jobs AS j ON sj.job_id = j.id  WHERE ( ( post_status = 'draft' ) )")->willReturn([$this->result]);

        $x = $this->subject;
        $x->expects($this->once())->method('fetchData')->willReturn([]);
        $x->method('getDbal')->willReturn($db);

        $block = ConditionBlock::getConditionBlock();
        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'post_status', ['draft']));
        $x->searchByCondition($block);
    }

    public function testSearch_ConditionBlockWithBlocks()
    {
        $searchField = SubmissionEntity::FIELD_STATUS;
        $searchValue = SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS;

        $db = $this->db;
        $db->expects($this->once())->method('fetch')->with("SELECT COUNT(DISTINCT s.id) AS cnt\n     FROM wp_smartling_submissions AS s\n        LEFT JOIN wp_smartling_submissions_jobs AS sj ON s.id = sj.submission_id\n        LEFT JOIN wp_smartling_jobs AS j ON sj.job_id = j.id  WHERE ( ( ( s.$searchField = '$searchValue' ) ) )")->willReturn([$this->result]);

        $x = $this->subject;
        $x->expects($this->once())->method('fetchData')->willReturn([]);
        $x->method('getDbal')->willReturn($db);

        $conditionBlock = ConditionBlock::getConditionBlock();
        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $searchField, [$searchValue]));
        $block = ConditionBlock::getConditionBlock();
        $block->addConditionBlock($conditionBlock);
        $x->searchByCondition($block);
    }

    public function testSearch_ConditionBlockWithBlocksAndConditions()
    {
        $searchField1 = SubmissionEntity::FIELD_STATUS;
        $searchValue1 = SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS;
        $searchField2 = JobEntity::FIELD_JOB_NAME;
        $searchValue2 = 'Test';

        $db = $this->db;
        $db->expects($this->once())->method('fetch')->with("SELECT COUNT(DISTINCT s.id) AS cnt\n     FROM wp_smartling_submissions AS s\n        LEFT JOIN wp_smartling_submissions_jobs AS sj ON s.id = sj.submission_id\n        LEFT JOIN wp_smartling_jobs AS j ON sj.job_id = j.id  WHERE ( ( j.$searchField2 = '$searchValue2' AND ( s.$searchField1 = '$searchValue1' ) ) )")->willReturn([$this->result]);

        $x = $this->subject;
        $x->expects($this->once())->method('fetchData')->willReturn([]);
        $x->method('getDbal')->willReturn($db);

        $conditionBlock = ConditionBlock::getConditionBlock();
        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $searchField1, [$searchValue1]));
        $block = ConditionBlock::getConditionBlock();
        $block->addConditionBlock($conditionBlock);
        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $searchField2, [$searchValue2]));
        $x->searchByCondition($block);
    }

    public function testFindSubmissionsForUploadJob()
    {
        $db = $this->db;
        $x = $this->subject;
        $x->method('getDbal')->willReturn($db);
        $x->expects($this->once())->method('fetchData')->with("SELECT `id`, `source_title`, `source_blog_id`, `source_content_hash`, `content_type`, `source_id`, `file_uri`, `target_locale`, `target_blog_id`, `target_id`, `submitter`, `submission_date`, `applied_date`, `approved_string_count`, `completed_string_count`, `excluded_string_count`, `total_string_count`, `word_count`, `status`, `is_locked`, `is_cloned`, `last_modified`, `outdated`, `last_error`, `batch_uid`, `locked_fields` FROM `wp_smartling_submissions` WHERE ( `status` = 'New' AND `is_locked` = '0' AND `batch_uid` <> '' ) LIMIT 0,1")->willReturn([]);

        $x->findSubmissionsForUploadJob();
    }

    public function testStoreEmptyEntity()
    {
        $x = $this->subject;
        $entity = new SubmissionEntity();
        $this->assertSame($entity, $x->storeEntity($entity));
    }

    public function testStoreEntityQuery()
    {
        $title = 'Test';
        $sourceBlogId = 1;
        $db = $this->db;
        $db->expects($this->once())->method('query')
            ->with("INSERT  INTO `wp_smartling_submissions` (`source_title`, `source_blog_id`) VALUES ('$title','$sourceBlogId')")
            ->willReturn(true);
        $x = $this->subject;
        $x->method('getDbal')->willReturn($db);
        $entity = new SubmissionEntity();
        $entity->setSourceBlogId($sourceBlogId);
        $entity->setSourceTitle($title);

        $this->expectException(\TypeError::class); // no post type, but we don't care
        $x->storeEntity($entity);
    }

    public function testUpdateEntityQuery()
    {
        $title = 'Test';
        $sourceBlogId = 1;
        $submissionId = 17;
        $db = $this->db;
        $db->expects($this->once())->method('query')
            ->with("UPDATE `wp_smartling_submissions` SET `source_title` = '$title', `source_blog_id` = '$sourceBlogId', `source_content_hash` = '', `content_type` = '', `source_id` = '', `file_uri` = '', `target_locale` = '', `target_blog_id` = '', `target_id` = '', `submitter` = '', `submission_date` = '', `applied_date` = '', `approved_string_count` = '', `completed_string_count` = '', `excluded_string_count` = '', `total_string_count` = '', `word_count` = '', `status` = '', `is_locked` = '', `is_cloned` = '', `last_modified` = '', `outdated` = '', `last_error` = '', `batch_uid` = '', `locked_fields` = '' WHERE ( `id` = '$submissionId' ) LIMIT 1")
            ->willReturn(true);
        $x = $this->subject;
        $x->method('getDbal')->willReturn($db);
        $entity = new SubmissionEntity();
        $entity->setId($submissionId);
        $entity->setSourceBlogId($sourceBlogId);
        $entity->setSourceTitle($title);

        $x->storeEntity($entity);
    }

    public function testDeleteEntityQuery()
    {
        $title = 'Test';
        $sourceBlogId = 1;
        $submissionId = 17;
        $entity = new SubmissionEntity();
        $entity->setId($submissionId);
        $entity->setSourceBlogId($sourceBlogId);
        $entity->setSourceTitle($title);
        $db = $this->db;
        $db->expects($this->once())->method('query')->with("DELETE FROM `wp_smartling_submissions` WHERE ( `id` = '$submissionId' )");
        $submissionsJobsManager = $this->createMock(SubmissionsJobsManager::class);
        $submissionsJobsManager->expects($this->once())->method('deleteBySubmissionId')->with($submissionId);

        $x = $this->getMockBuilder(SubmissionManager::class)->setConstructorArgs([
            $db,
            20,
            $this->createMock(EntityHelper::class),
            $this->createMock(JobManager::class),
        $submissionsJobsManager,
        ])->onlyMethods(['fetchData', 'getLogger'])->getMock();
        $x->method('getLogger')->willReturn(new NullLogger());
        $x->expects($this->once())->method('fetchData')->with("SELECT s.id, s.source_title, s.source_blog_id, s.source_content_hash, s.content_type, s.source_id, s.file_uri, s.target_locale, s.target_blog_id, s.target_id, s.submitter, s.submission_date, s.applied_date, s.approved_string_count, s.completed_string_count, s.excluded_string_count, s.total_string_count, s.word_count, s.status, s.is_locked, s.is_cloned, s.last_modified, s.outdated, s.last_error, s.batch_uid, s.locked_fields, j.job_name, j.job_uid, j.project_uid, j.created, j.modified     FROM wp_smartling_submissions AS s\n        LEFT JOIN wp_smartling_submissions_jobs AS sj ON s.id = sj.submission_id\n        LEFT JOIN wp_smartling_jobs AS j ON sj.job_id = j.id  WHERE ( ( s.id IN('$submissionId') ) )")->willReturn([$entity]);

        $x->delete($entity);
    }

    public function testGetGroupedIdsByFileUri()
    {
        $db = $this->db;
        $db->expects($this->once())->method('fetch')->with("SELECT `file_uri` AS `fileUri`, GROUP_CONCAT(`id`) AS `ids` FROM `wp_smartling_submissions` WHERE ( `status` IN('In Progress', 'Completed') ) GROUP BY `file_uri`");
        $x = $this->subject;
        $x->method('getDbal')->willReturn($db);
        $x->getGroupedIdsByFileUri();
    }
}
