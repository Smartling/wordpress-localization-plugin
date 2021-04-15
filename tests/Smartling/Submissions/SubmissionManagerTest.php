<?php

namespace {
    if (!defined('OBJECT')) {
        define("OBJECT", 'OBJECT');
    }
}

namespace Smartling\Tests\Smartling\Submissions {

    use PHPUnit\Framework\TestCase;
    use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
    use Smartling\Helpers\EntityHelper;
    use Smartling\Helpers\QueryBuilder\Condition\Condition;
    use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
    use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
    use Smartling\Jobs\JobInformationEntity;
    use Smartling\Jobs\JobInformationManager;
    use Smartling\Submissions\SubmissionManager;

    class SubmissionManagerTest extends TestCase
    {
        public function testBuildQuery()
        {
            $db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
            $db->method('completeTableName')->willReturnArgument(0);
            $x = new class(
                $db,
                10,
                $this->createMock(EntityHelper::class),
                $this->createMock(JobInformationManager::class)
            ) extends SubmissionManager {
                public function buildQuery(
                    ?string $contentType,
                    ?string $status,
                    ?int $outdatedFlag,
                    ?array $sortOptions,
                    ?array $pageOptions,
                    ConditionBlock $condition = null): string
                {
                    return parent::buildQuery('post', $status, $outdatedFlag, $sortOptions, $pageOptions, $condition);
                }
            };
            $contentType = 'post';
            $status = 'new';
            $outdatedFlag = 0;
            $condition = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
            $condition->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, JobInformationEntity::FIELD_BATCH_UID, ['']));
            $this->assertEquals(
                "SELECT s.id, s.source_title, s.source_blog_id, s.source_content_hash, s.content_type, s.source_id, s.file_uri, s.target_locale, s.target_blog_id, s.target_id, s.submitter, s.submission_date, s.applied_date, s.approved_string_count, s.completed_string_count, s.excluded_string_count, s.total_string_count, s.word_count, s.status, s.is_locked, s.is_cloned, s.last_modified, s.outdated, s.last_error, s.locked_fields, j.submission_id, j.batch_uid, j.job_name, j.job_uid, j.project_uid FROM smartling_submissions AS s INNER JOIN smartling_jobs AS j ON s.id = j.submission_id  WHERE ( s.content_type = 'post' AND s.status = 'new' AND s.outdated = '0' AND ( j.batch_uid = '' ) ) AND (j.id = (SELECT MAX(id) FROM smartling_jobs WHERE submission_id = s.id)) GROUP BY s.id ORDER BY j.id DESC",
                $x->buildQuery($contentType, $status, $outdatedFlag, null, null, $condition)
            );
        }
    }
}
