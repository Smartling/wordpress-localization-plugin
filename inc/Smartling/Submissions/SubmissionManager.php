<?php

namespace Smartling\Submissions;

use Smartling\DbAl\EntityManagerAbstract;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\WordpressUserHelper;
use Smartling\Jobs\JobInformationManager;
use Smartling\Jobs\SubmissionJobEntity;
use Smartling\Jobs\SubmissionJobManager;

class SubmissionManager extends EntityManagerAbstract
{
    private JobInformationManager $jobInformationManager;
    private SubmissionJobManager $submissionJobManager;

    public function getSubmissionStatusLabels(): array
    {
        return SubmissionEntity::getSubmissionStatusLabels();
    }

    public function getDefaultSubmissionStatus(): string
    {
        return SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS;
    }

    public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $dbal, int $pageSize, EntityHelper $entityHelper, JobInformationManager $jobInformationManager, SubmissionJobManager $submissionJobManager)
    {
        $siteHelper = $entityHelper->getSiteHelper();
        $proxy = $entityHelper->getConnector();
        parent::__construct($dbal, $pageSize, $siteHelper, $proxy);
        $this->jobInformationManager = $jobInformationManager;
        $this->submissionJobManager = $submissionJobManager;
    }

    /**
     * @param mixed $contentType
     */
    private function isValidContentType($contentType): bool
    {
        return
            null === $contentType
            || array_key_exists($contentType, WordpressContentTypeHelper::getReverseMap());
    }

    protected function dbResultToEntity(array $dbRow): SubmissionEntity
    {
        $result = SubmissionEntity::fromArray($dbRow, $this->getLogger());
        if ($result->getId() !== null) {
            $jobInfo = $this->jobInformationManager->getBySubmissionId($result->getId());
            if ($jobInfo !== null) {
                $result->setJobInfo($jobInfo);
            }
        }

        return $result;
    }

    private function isValidRequest(?string $contentType, array $sortOptions, ?array $pageOptions): bool
    {
        $validRequest = $this->isValidContentType($contentType) &&
            QueryBuilder::validatePageOptions($pageOptions) &&
            QueryBuilder::validateSortOptions(array_keys(SubmissionEntity::getFieldDefinitions()), $sortOptions);

        return ($validRequest === true);
    }

    public function submissionExists(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId): bool
    {
        return 1 === count($this->find([
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_SOURCE_ID => $contentId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ], 1));
    }

    public function submissionExistsNoLastError(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId): bool
    {
        return 1 === count($this->find([
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_LAST_ERROR => '',
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_SOURCE_ID => $contentId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ], 1));
    }

    /**
     * @return SubmissionEntity[]
     */
    public function searchByCondition(
        ConditionBlock $block,
        ?string $contentType = null,
        ?string $status = null,
        ?int $outdatedFlag = null,
        array $sortOptions = [],
        ?array $pageOptions = null,
        int &$totalCount = 0
    ): array
    {
        $result = [];

        if ($this->isValidRequest($contentType, $sortOptions, $pageOptions)) {
            [$totalCount, $result] = $this->getTotalCountAndResult($contentType, $status, $outdatedFlag, $sortOptions, count($block->getConditions()) > 0 ? $block : null, $pageOptions);
        }

        return $result;
    }

    private function getTotalByFieldInValues(array $conditions): int
    {
        $block = ConditionBlock::getConditionBlock();

        foreach ($conditions as $field => $values) {
            $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_IN, $field, $values));
        }

        $countQuery = $this->buildCountQuery(
            null,
            null,
            null,
            $block
        );

        $totalCount = $this->getDbal()->fetch($countQuery);

        return (int)$totalCount[0]->cnt;
    }

    public function getTotalInUploadQueue(): int
    {
        return $this->getTotalByFieldInValues(
            [
                SubmissionEntity::FIELD_STATUS => [
                    SubmissionEntity::SUBMISSION_STATUS_NEW,
                ],
                SubmissionEntity::FIELD_IS_LOCKED => [0],
            ]);
    }

    public function getTotalInCheckStatusHelperQueue(): int
    {
        return $this->getTotalByFieldInValues(
            [
                SubmissionEntity::FIELD_STATUS => [
                    SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                ],
                SubmissionEntity::FIELD_IS_LOCKED => [0],
            ]
        );
    }


    public function searchByBatchUid(string $batchUid): array
    {
        $block = ConditionBlock::getConditionBlock();
        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, SubmissionEntity::FIELD_BATCH_UID, [$batchUid]));
        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
            SubmissionEntity::FIELD_STATUS, [SubmissionEntity::SUBMISSION_STATUS_NEW]));
        $total = 0;

        return $this->searchByCondition($block, null, null, null, [], null, $total);
    }

    /**
     * Gets SubmissionEntity from database by primary key
     * alias to getEntities
     *
     * @return null|SubmissionEntity[]
     */
    public function getEntityById(int $id): ?array
    {
        $query = $this->buildSelectQuery([SubmissionEntity::FIELD_ID => $id]);

        $obj = $this->fetchData($query);

        if (is_array($obj) && empty($obj)) {
            $obj = null;
        }

        return $obj;
    }

    public function buildSelectQuery(array $where): string
    {
        $whereOptions = ConditionBlock::getConditionBlock();
        foreach ($where as $key => $item) {
            $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $key, [$item]);
            $whereOptions->addCondition($condition);
        }

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName(SubmissionEntity::getTableName()),
            array_keys(SubmissionEntity::getFieldDefinitions()),
            $whereOptions,
        );
        $this->logQuery($query);

        return $query;
    }

    public function buildCountQuery(?string $contentType, ?string $status, ?int $outdatedFlag, ConditionBlock $baseCondition = null): string
    {
        $whereOptions = null;

        if (null !== $contentType || null !== $status || null !== $outdatedFlag || null !== $baseCondition) {
            $whereOptions = ConditionBlock::getConditionBlock();
            if ($baseCondition instanceof ConditionBlock) {
                $whereOptions->addConditionBlock($baseCondition);
            }

            if (!is_null($contentType)) {
                $whereOptions->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_EQ,
                        SubmissionEntity::FIELD_CONTENT_TYPE,
                        [$contentType]
                    )
                );
            }

            if (!is_null($status)) {
                $whereOptions->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_EQ,
                        SubmissionEntity::FIELD_STATUS,
                        [$status]
                    )
                );
            }

            if (!is_null($outdatedFlag)) {
                $whereOptions->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_EQ,
                        SubmissionEntity::FIELD_OUTDATED,
                        [$outdatedFlag]
                    )
                );
            }
        }

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName(SubmissionEntity::getTableName()),
            [['COUNT(*)' => 'cnt']],
            $whereOptions,
        );

        $this->logQuery($query);

        return $query;
    }

    /**
     * Search submission by params
     *
     * @param array $params
     * @param int $limit (if 0 - unlimited)
     *
     * @return SubmissionEntity[]
     */
    public function find(array $params = [], int $limit = 0): array
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);

        foreach ($params as $field => $value) {
            if (is_array($value)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_IN, $field, $value);
            } else {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $field, [$value]);
            }

            $block->addCondition($condition);
        }


        $pageOptions = 0 === $limit ? null : ['limit' => $limit, 'page' => 1];

        $query = $this->buildQuery(null, null, null, [], $pageOptions, $block);

        return $this->fetchData($query);
    }


    /**
     * Looks for submissions with status = 'New' AND (is_cloned = 1 or batch_uid <> '') AND is_locked = 0
     * @return SubmissionEntity[]
     */
    public function findSubmissionsForUploadJob(): array
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);

        $blockBase = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $blockBase->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
            SubmissionEntity::FIELD_STATUS, [SubmissionEntity::SUBMISSION_STATUS_NEW]));
        $blockBase->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
            SubmissionEntity::FIELD_IS_LOCKED, [0]));
        $block->addConditionBlock($blockBase);

        $blockAlt = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR);
        $blockAlt->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
            SubmissionEntity::FIELD_IS_CLONED, [1]));
        $blockAlt->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_NOT_EQ,
            SubmissionEntity::FIELD_BATCH_UID, ['']));
        $block->addConditionBlock($blockAlt);

        $pageOptions = ['limit' => 1, 'page' => 1];

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName(SubmissionEntity::getTableName()),
            array_keys(SubmissionEntity::getFieldDefinitions()),
            $block,
            [],
            $pageOptions
        );

        return $this->fetchData($query);
    }

    /**
     * @param int[] $ids
     * @return SubmissionEntity[]
     */
    public function findByIds(array $ids): array
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_IN, SubmissionEntity::FIELD_ID, $ids);
        $block->addCondition($condition);
        $query = $this->buildQuery(null, null, null, [], null, $block);

        return $this->fetchData($query);
    }

    protected function buildQuery(
        ?string $contentType,
        ?string $status,
        ?int $outdatedFlag,
        ?array $sortOptions,
        ?array $pageOptions,
        ConditionBlock $baseCondition = null
    ): string
    {
        if (null !== $contentType || null !== $status || null !== $outdatedFlag || null !== $baseCondition) {
            $whereOptions = ConditionBlock::getConditionBlock();
            if ($baseCondition instanceof ConditionBlock) {
                $whereOptions->addConditionBlock($baseCondition);
            }

            if (!is_null($contentType)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
                    SubmissionEntity::FIELD_CONTENT_TYPE, [$contentType]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($status)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
                    SubmissionEntity::FIELD_STATUS, [$status]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($outdatedFlag)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
                    SubmissionEntity::FIELD_OUTDATED, [$outdatedFlag]);
                $whereOptions->addCondition($condition);
            }
        } else {
            $whereOptions = $baseCondition;
        }

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName(SubmissionEntity::getTableName()),
            array_keys(SubmissionEntity::getFieldDefinitions()),
            $whereOptions,
            $sortOptions,
            $pageOptions,
        );

        $this->logQuery($query);

        return $query;
    }

    public function getColumnsLabels(): array
    {
        return SubmissionEntity::getFieldLabels();
    }

    public function getSortableFields(): array
    {
        return SubmissionEntity::getSortableFields();
    }

    public static function getChangedFields(SubmissionEntity $submission): array
    {
        return in_array($submission->getId(), [0, null], true) // Inserting submission?
            ? $submission->toArray(false)
            : $submission->getChangedFields();
    }

    /**
     * Stores SubmissionEntity to database. (fills id if needed)
     */
    public function storeEntity(SubmissionEntity $entity): SubmissionEntity
    {
        $originalSubmission = json_encode($entity->toArray(false), JSON_THROW_ON_ERROR);
        $this->getLogger()->debug(vsprintf('Starting saving submission: %s', [$originalSubmission]));
        $submissionId = $entity->id;

        $is_insert = in_array($submissionId, [0, null], true);

        $fields = static::getChangedFields($entity);

        foreach ($fields as $field => $value) {
            if (null === $value) {
                unset($fields[$field]);
            }
        }

        if (array_key_exists(SubmissionEntity::FIELD_ID, $fields)) {
            unset ($fields[SubmissionEntity::FIELD_ID]);
        }

        if (0 === count($fields)) {
            $this->getLogger()->debug(vsprintf('No data has been modified since load. Skipping save', []));

            return $entity;
        }

        $tableName = $this->getDbal()->completeTableName(SubmissionEntity::getTableName());

        if ($is_insert) {
            $storeQuery = QueryBuilder::buildInsertQuery($tableName, $fields);
        } else {
            // update
            $conditionBlock = ConditionBlock::getConditionBlock();
            $conditionBlock->addCondition(
                Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [$submissionId])
            );
            $storeQuery = QueryBuilder::buildUpdateQuery($tableName, $fields, $conditionBlock, ['limit' => 1]);
        }

        // log store query before execution
        $this->logQuery($storeQuery);

        $result = $this->getDbal()->query($storeQuery);

        if (false === $result) {
            $message = vsprintf(
                'Failed saving submission entity to database with following error message: %s',
                [
                    $this->getDbal()->getLastErrorMessage(),
                ]
            );

            $this->getLogger()->error($message);
        } elseif ($is_insert) {
            $entityFields = $entity->toArray(false);
            $entityFields[SubmissionEntity::FIELD_ID] = $this->getDbal()->getLastInsertedId();
            // update reference to entity
            $entity = SubmissionEntity::fromArray($entityFields, $this->getLogger());
        }

        if ($entity->getJobInfo()->getJobUid() !== '') { // TODO fix
            $jobId = $this->jobInformationManager->store($entity->getJobInfo())->getId();
            $this->submissionJobManager->store(new SubmissionJobEntity($jobId, $entity->getId()));
        }

        $this->getLogger()->debug(
            vsprintf('Finished saving submission: %s. id=%s', [$originalSubmission, $entity->getId()])
        );

        return $entity;
    }

    public function createSubmission(array $fields): SubmissionEntity
    {
        return SubmissionEntity::fromArray($fields, $this->getLogger());
    }

    /**
     * Loads from database or creates a new instance of SubmissionEntity
     */
    public function getSubmissionEntity(
        string $contentType,
        int $sourceBlog,
        int $sourceEntity,
        int $targetBlog,
        LocalizationPluginProxyInterface $localizationProxy,
        ?int $targetEntity = null
    ): SubmissionEntity
    {
        $params = [
            SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlog,
            SubmissionEntity::FIELD_SOURCE_ID => $sourceEntity,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlog,
        ];

        if (null !== $targetEntity) {
            $params[SubmissionEntity::FIELD_TARGET_ID] = $targetEntity;
        }

        $entities = $this->find($params);

        if (count($entities) > 0) {
            $entity = ArrayHelper::first($entities);
            $entity->setLastError('');
        } else {
            $entity = $this->createSubmission($params);
            $entity->setTargetLocale($localizationProxy->getBlogLocaleById($targetBlog));
            $entity->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $entity->setSubmitter(WordpressUserHelper::getUserLogin());
            $entity->setSourceTitle('no title');
            $entity->setSubmissionDate(DateTimeHelper::nowAsString());
        }

        return $entity;
    }

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @return SubmissionEntity[]
     */
    public function storeSubmissions(array $submissions): array
    {
        $newList = [];

        foreach ($submissions as $submission) {
            $newList[] = $this->storeEntity($submission);
        }

        return $newList;
    }

    public function delete(SubmissionEntity $submission): void
    {
        $this->getLogger()->debug(
            vsprintf(
                'Preparing to delete submission %s',
                [
                    var_export($submission->toArray(false), true),
                ]
            )
        );

        $submissionId = (int)$submission->getId();

        if (0 < $submissionId) {
            $this->getLogger()->debug(
                vsprintf(
                    'Looking for requested submission id=%s in the database.',
                    [
                        $submissionId,
                    ]
                )
            );

            $storedSubmissions = $this->findByIds([$submissionId]);

            if (0 < count($storedSubmissions)) {
                $this->getLogger()->debug(
                    vsprintf(
                        'Found submission in database: %s.',
                        [
                            var_export(ArrayHelper::first($storedSubmissions)->toArray(false), true),
                        ]
                    )
                );

                $block = ConditionBlock::getConditionBlock();
                $block->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_EQ,
                        SubmissionEntity::FIELD_ID,
                        [
                            $submission->getId(),
                        ]
                    )
                );

                $query = QueryBuilder::buildDeleteQuery(
                    $this->getDbal()->completeTableName(
                        SubmissionEntity::getTableName()
                    ),
                    $block
                );

                $this->getLogger()->info(
                    sprintf('Executing delete query for submission id=%d (sourceBlog=%d, sourceId=%d, targetBlog=%d, targetId=%d)', $submissionId, $submission->getSourceBlogId(), $submission->getSourceId(), $submission->getTargetBlogId(), $submission->getTargetId())
                );
                $this->getDbal()->query($query);
                // TODO delete job information
            } else {
                $this->getLogger()->debug(
                    vsprintf(
                        'No submissions found with id=%s.',
                        [
                            $submissionId,
                        ]
                    )
                );

            }
        } else {
            $this->getLogger()->debug('Submission id must be > 0. Skipping.');
        }
    }

    public function setErrorMessage(SubmissionEntity $submission, string $message): SubmissionEntity
    {
        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
        $submission->setLastError($message);

        return $this->storeEntity($submission);
    }

    public function getGroupedIdsByFileUri()
    {
        $this->getDbal()->query('SET group_concat_max_len=2048000');
        $block = ConditionBlock::getConditionBlock();
        $block->addCondition(
            Condition::getCondition(
                ConditionBuilder::CONDITION_SIGN_IN,
                SubmissionEntity::FIELD_STATUS,
                [
                    SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                ]
            )
        );
        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName(SubmissionEntity::getTableName()),
            [
                [SubmissionEntity::FIELD_FILE_URI => 'fileUri'],
                ['GROUP_CONCAT(`id`)' => 'ids'],
            ],
            $block,
            [],
            null,
            ['file_uri']
        );

        return $this->getDbal()->fetch($query, ARRAY_A);
    }

    private function getTotalCountAndResult(?string $contentType, ?string $status, ?int $outdatedFlag, array $sortOptions = null, ConditionBlock $block = null, array $pageOptions = null): array
    {
        $totalCount = $this->getDbal()->fetch($this->buildCountQuery($contentType, $status, $outdatedFlag, $block));

        return [(int)$totalCount[0]->cnt, $this->fetchData($this->buildQuery($contentType, $status, $outdatedFlag, $sortOptions, $pageOptions, $block))];
    }
}
