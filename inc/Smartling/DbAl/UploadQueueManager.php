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

    public const MAX_ATTEMPTS = 3;

    public const STALE_CLAIM_SECONDS = 900;

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
        $staleClaimCondition = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR);
        $staleClaimCondition->addCondition(new Condition(
            ConditionBuilder::CONDITION_IS_NULL,
            'q.' . UploadQueueEntity::FIELD_CLAIMED,
            [],
            false,
        ));
        $staleClaimCondition->addCondition(new Condition(
            ConditionBuilder::CONDITION_SIGN_LESS,
            'q.' . UploadQueueEntity::FIELD_CLAIMED,
            $this->getStaleClaimThreshold(),
            false,
        ));

        $query = sprintf(<<<'SQL'
select q.%1$s, q.%2$s, q.%3$s, q.%9$s, q.%10$s from %7$s q left join %8$s s
    on if(locate(',', q.%2$s), left(%2$s, locate(',', %2$s) - 1), %2$s) = s.%4$s
    where s.%5$s = %6$d and %11$s
SQL,
            UploadQueueEntity::FIELD_ID,
            UploadQueueEntity::FIELD_SUBMISSION_IDS,
            UploadQueueEntity::FIELD_BATCH_UID,
            SubmissionEntity::FIELD_ID,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID,
            $blogId,
            $this->db->completeTableName(UploadQueueEntity::getTableName()),
            $this->db->completeTableName(SubmissionEntity::getTableName()),
            UploadQueueEntity::FIELD_CLAIMED,
            UploadQueueEntity::FIELD_ATTEMPTS,
            $staleClaimCondition,
        );
        while (($row = $this->db->getRowArray($query)) !== null) {
            $queueId = (int)$row[UploadQueueEntity::FIELD_ID];
            $attempts = (int)($row[UploadQueueEntity::FIELD_ATTEMPTS] ?? 0);
            $locales = new IntStringPairCollection();
            $submissions = [];
            $existingSubmissions = [];
            $unprocessable = false;
            foreach (IntegerIterator::fromString($row[UploadQueueEntity::FIELD_SUBMISSION_IDS]) as $submissionId) {
                $submission = $this->submissionManager->getEntityById($submissionId);
                if ($submission === null) {
                    $this->getLogger()->warning("Discarding upload queue item id=$queueId: submissionId=$submissionId no longer exists");
                    $unprocessable = true;
                    continue;
                }

                $existingSubmissions[] = $submission;

                $locale = $this->getSmartlingLocale($submission);
                if ($locale === null) {
                    $this->getLogger()->warning("Discarding upload queue item id=$queueId: unable to resolve target locale for submissionId=$submissionId, targetBlogId={$submission->getTargetBlogId()}");
                    $unprocessable = true;
                    continue;
                }

                $locales = $locales->add([new IntStringPair($submission->getId(), $locale)]);
                $submissions[] = $submission;
            }

            if ($unprocessable) {
                // The whole row is grouped by shared content, so one unresolvable submission
                // takes the rest down with it. They must not vanish silently: every submission
                // that still exists gets a visible error instead of being left in New status
                // with no queue row and no explanation.
                $this->discardQueueItem(
                    $queueId,
                    $existingSubmissions,
                    'Upload queue item discarded: unable to resolve one or more submissions grouped with this item, see log for details.',
                );
                continue;
            }

            if ($attempts >= self::MAX_ATTEMPTS) {
                $message = sprintf(
                    'Upload abandoned after %d attempts. The upload process most likely terminated unexpectedly (fatal error, timeout or out of memory) while handling this content.',
                    $attempts,
                );
                $this->getLogger()->error("Failing upload queue item id=$queueId: $message");
                $this->discardQueueItem($queueId, $submissions, $message);
                continue;
            }

            $this->claim($queueId, $attempts);

            return new UploadQueueItem($submissions, $row[UploadQueueEntity::FIELD_BATCH_UID], $locales, $queueId);
        }

        return null;
    }

    /**
     * @param SubmissionEntity[] $submissions
     */
    private function discardQueueItem(int $queueId, array $submissions, string $errorMessage): void
    {
        foreach ($submissions as $submission) {
            $this->submissionManager->setErrorMessage($submission, $errorMessage);
        }
        $this->delete($queueId);
    }

    private function getStaleClaimThreshold(): string
    {
        return DateTimeHelper::dateTimeToString(
            (new \DateTime('now', new \DateTimeZone(DateTimeHelper::TIMEZONE_UTC)))
                ->modify('-' . self::STALE_CLAIM_SECONDS . ' seconds')
        );
    }

    public function complete(UploadQueueItem $item): void
    {
        $id = $item->getId();
        if ($id !== null) {
            $this->delete($id);
        }
    }

    private function claim(int $id, int $attempts): void
    {
        $this->db->query(QueryBuilder::buildUpdateQuery(
            $this->tableName,
            [
                UploadQueueEntity::FIELD_CLAIMED => DateTimeHelper::nowAsString(),
                UploadQueueEntity::FIELD_ATTEMPTS => $attempts + 1,
            ],
            $this->idCondition($id),
        ));
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
        $this->db->query(QueryBuilder::buildDeleteQuery($this->tableName, $this->idCondition($id)));
    }

    private function idCondition(int $id): ConditionBlock
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $block->addCondition(new Condition(ConditionBuilder::CONDITION_SIGN_EQ, UploadQueueEntity::FIELD_ID, $id));

        return $block;
    }

}
