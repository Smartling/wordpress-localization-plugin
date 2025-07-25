<?php

namespace Smartling\DbAl;

use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Models\IntegerIterator;
use Smartling\Models\IntStringPair;
use Smartling\Models\IntStringPairCollection;
use Smartling\Models\UploadQueueEntity;
use Smartling\Models\UploadQueueItem;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class UploadQueueManager {
    use LoggerSafeTrait;
    private string $tableName;
    public function __construct(
        private ApiWrapperInterface $api,
        private SettingsManager $settingsManager,
        private SmartlingToCMSDatabaseAccessWrapperInterface $db,
        private SubmissionManager $submissionManager,
    ) {
        $this->tableName = $db->completeTableName(UploadQueueEntity::TABLE_NAME);
    }

    public function count(): int
    {
        return (int)$this->db->getRowArray(
            "SELECT SUM(LENGTH(submission_ids) - LENGTH(REPLACE(submission_ids, ',', '')) + 1) cnt from $this->tableName"
        )['cnt'];
    }

    public function dequeue(int $blogId): ?UploadQueueItem
    {
        // Get queue items with first submission having its source blog id = $blogId.
        // It's impossible to create a single queue item with submissions from multiple source blog ids,
        // so only checking one is enough.
        $query = sprintf(<<<'SQL'
select q.%1$s, q.%2$s, q.%3$s from %7$s q left join %8$s s
    on if(locate(',', q.%2$s), left(%2$s, locate(',', %2$s) - 1), %2$s) = s.%4$s
    where s.%5$s = %6$d
SQL,
            UploadQueueEntity::FIELD_ID,
            UploadQueueEntity::FIELD_SUBMISSION_IDS,
            UploadQueueEntity::FIELD_BATCH_UID,
            SubmissionEntity::FIELD_ID,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID,
            $blogId,
            $this->db->completeTableName(UploadQueueEntity::getTableName()),
            $this->db->completeTableName(SubmissionEntity::getTableName()),
        );
        while (($row = $this->db->getRowArray($query)) !== null) {
            $this->delete($row[UploadQueueEntity::FIELD_ID]);
            $locales = new IntStringPairCollection();
            $submissions = [];
            foreach (IntegerIterator::fromString($row[UploadQueueEntity::FIELD_SUBMISSION_IDS]) as $submissionId) {
                $submission = $this->submissionManager->getEntityById($submissionId);
                if ($submission === null) {
                    continue 2;
                }

                $locale = $this->getSmartlingLocale($submission);
                if ($locale === null) {
                    continue 2;
                }

                $locales = $locales->add([new IntStringPair($submission->getId(), $locale)]);
                $submissions[] = $submission;
            }

            return new UploadQueueItem($submissions, $row[UploadQueueEntity::FIELD_BATCH_UID], $locales);
        }

        return null;
    }

    public function enqueue(IntegerIterator $submissionIds, string $batchUid): void
    {
        $this->db->withTransaction(function () use ($batchUid, $submissionIds) {
            $ids = $submissionIds->getArrayCopy();
            while (count($ids) > 0) {
                $id = $ids[0];
                $submission = $this->submissionManager->getEntityById($id);
                if ($submission === null) {
                    array_shift($ids);
                    continue;
                }
                $sameSourceSubmissions = $this->submissionManager->find([
                    SubmissionEntity::FIELD_CONTENT_TYPE => $submission->getContentType(),
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                    SubmissionEntity::FIELD_SOURCE_ID => $submission->getSourceId(),
                ]);
                $sameSourceIds = array_intersect(array_map(static function (SubmissionEntity $entity) {
                    return $entity->getId();
                }, $sameSourceSubmissions), $ids);
                $this->db->query(QueryBuilder::buildInsertQuery($this->tableName, [
                    UploadQueueEntity::FIELD_BATCH_UID => $batchUid,
                    UploadQueueEntity::FIELD_CREATED => DateTimeHelper::nowAsString(),
                    UploadQueueEntity::FIELD_SUBMISSION_IDS => (new IntegerIterator($sameSourceIds))->serialize(),
                ]));
                $ids = array_values(array_diff($ids, $sameSourceIds));
            }
        });
    }

    public function length(): int
    {
        return (int)$this->db->getRowArray("SELECT COUNT(*) cnt FROM $this->tableName")['cnt'];
    }

    public function purge(): void
    {
        $items = $this->db->getResultsArray(QueryBuilder::buildSelectQuery($this->tableName, [
            UploadQueueEntity::FIELD_ID,
            UploadQueueEntity::FIELD_BATCH_UID,
            UploadQueueEntity::FIELD_SUBMISSION_IDS,
        ]));
        $this->db->query("TRUNCATE $this->tableName");
        $profiles = [];
        foreach ($items as $item) {
            foreach (IntegerIterator::fromString($item[UploadQueueEntity::FIELD_SUBMISSION_IDS]) as $submissionId) {
                $submission = $this->submissionManager->getEntityById($submissionId);
                if ($submission === null) {
                    continue;
                }
                if (!array_key_exists($submission->getSourceBlogId(), $profiles)) {
                    try {
                        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
                    } catch (SmartlingDbException) {
                        $profile = null;
                    }
                    $profiles[$submission->getSourceBlogId()] = $profile;
                }
                $profile = $profiles[$submission->getSourceBlogId()];
                if (!$profile instanceof ConfigurationProfileEntity) {
                    continue;
                }

                $batchUid = $item[UploadQueueEntity::FIELD_BATCH_UID];
                try {
                    $this->api->cancelBatchFile($profile, $batchUid, $submission->getFileUri());
                } catch (SmartlingApiException) {
                    continue;
                }
            }
        }
    }

    private function getSmartlingLocale(SubmissionEntity $submission): ?string
    {
        try {
            return $this->settingsManager->getSmartlingLocaleBySubmission($submission);
        } catch (SmartlingDbException) {
            // profile not found, return null
        }

        return null;
    }

    private function delete(int $id): void
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueEntity::FIELD_ID, $id));

        $this->db->query(QueryBuilder::buildDeleteQuery($this->tableName, $block));
    }

}
