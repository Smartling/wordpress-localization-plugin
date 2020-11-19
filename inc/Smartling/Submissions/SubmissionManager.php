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

/**
 * Class SubmissionManager
 * @package Smartling\Submissions
 */
class SubmissionManager extends EntityManagerAbstract
{
    /**
     * @return array
     */
    public function getSubmissionStatusLabels()
    {
        return SubmissionEntity::getSubmissionStatusLabels();
    }

    /**
     * @return array
     */
    public function getSubmissionStatuses()
    {
        return SubmissionEntity::$submissionStatuses;
    }

    /**
     * @return string
     */
    public function getDefaultSubmissionStatus()
    {
        return SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS;
    }

    /**
     * @var WordpressContentTypeHelper
     */
    private $helper;

    /**
     * @return WordpressContentTypeHelper
     */
    public function getHelper()
    {
        return $this->helper;
    }

    /**
     * @var EntityHelper
     */
    private $entityHelper;

    /**
     * @return EntityHelper
     */
    public function getEntityHelper()
    {
        return $this->entityHelper;
    }

    /**
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param int                                          $pageSize
     * @param EntityHelper                                 $entityHelper
     */
    public function __construct($dbal, $pageSize, $entityHelper)
    {
        $siteHelper = $entityHelper->getSiteHelper();
        $proxy = $entityHelper->getConnector();
        parent::__construct($dbal, $pageSize, $siteHelper, $proxy);
        $this->entityHelper = $entityHelper;
    }

    /**
     * @param mixed $contentType
     * @return bool
     */
    private function isValidContentType($contentType)
    {
        return
            null === $contentType
            || array_key_exists($contentType, WordpressContentTypeHelper::getReverseMap());
    }

    /**
     * @param array $dbRow
     * @return SubmissionEntity
     */
    protected function dbResultToEntity(array $dbRow)
    {
        return SubmissionEntity::fromArray($dbRow, $this->getLogger());
    }

    /**
     * @param string $contentType
     * @param $sortOptions
     * @param $pageOptions
     *
     * @return bool
     */
    private function isValidRequest($contentType, $sortOptions, $pageOptions)
    {
        $validRequest = $this->isValidContentType($contentType) &&
            QueryBuilder::validatePageOptions($pageOptions) &&
            QueryBuilder::validateSortOptions(array_keys(SubmissionEntity::getFieldDefinitions()), $sortOptions);

        return ($validRequest === true);
    }

    /**
     * @param null       $contentType
     * @param null       $status
     * @param null       $outdatedFlag
     * @param array      $sortOptions
     * @param null|array $pageOptions
     * @param null|int   $targetBlogId
     * @param int        $totalCount (reference)
     *
     * @return array of SubmissionEntity or empty array
     * $sortOptions is an array that keys are SubmissionEntity fields and values are 'ASC' or 'DESC'
     * or null if no sorting needed
     * e.g.: array('submission_date' => 'ASC', 'target_locale' => 'DESC')
     * $pageOptions is an array that has keys('page' and 'limit') for pagination output purposes purposes
     * or null if no pagination needed
     * e.g.: array('limit' => 20, 'page' => 1)
     */
    public function getEntities(
        $contentType = null,
        $status = null,
        $outdatedFlag = null,
        array $sortOptions = [],
        $pageOptions = null,
        $targetBlogId = null,
        & $totalCount = 0
    ) {
        $result = [];

        if ($this->isValidRequest($contentType, $sortOptions, $pageOptions)) {
            if (null !== $targetBlogId) {
                $block = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID, [$targetBlogId]);
                $block->addCondition($condition);
            } else {
                $block = null;
            }

            list($totalCount, $result) = $this->getTotalCountAndResult($contentType, $status, $outdatedFlag, $sortOptions, $block, $pageOptions);
        }

        return $result;
    }

    /**
     * @param string $contentType
     * @param int $sourceBlogId
     * @param int $contentId
     * @param int $targetBlogId
     * @return bool
     */
    public function submissionExists($contentType, $sourceBlogId, $contentId, $targetBlogId)
    {
        return 1 === count($this->find([
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_SOURCE_ID => $contentId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ], 1));
    }

    /**
     * @param string $contentType
     * @param int $sourceBlogId
     * @param int $contentId
     * @param int $targetBlogId
     * @return bool
     */
    public function submissionExistsNoLastError($contentType, $sourceBlogId, $contentId, $targetBlogId)
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
     * @param string      $searchText
     * @param array       $searchFields
     * @param null|string $contentType
     * @param null|string $status
     * @param null|bool   $outdatedFlag
     * @param array       $sortOptions
     * @param null|array  $pageOptions
     * @param int         $totalCount
     *
     * @return array
     */
    public function search(
        $searchText,
        array $searchFields = [],
        $contentType = null,
        $status = null,
        $outdatedFlag = null,
        array $sortOptions = [],
        $pageOptions = null,
        & $totalCount = 0
    ) {

        $searchText = trim($searchText);

        $totalCount = 0;

        $validRequest = !empty($searchFields) && $this->isValidRequest($contentType, $sortOptions, $pageOptions);

        $result = [];

        if ($validRequest) {

            $searchText = vsprintf('%%%s%%', [$searchText]);

            $block = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_OR);

            foreach ($searchFields as $field) {
                $block->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_LIKE,
                        $field,
                        [$searchText]
                    )
                );
            }

            list($totalCount, $result) = $this->getTotalCountAndResult($contentType, $status, $outdatedFlag, $sortOptions, $block, $pageOptions);
        }

        return $result;
    }

    /**
     * @param ConditionBlock|null $block
     * @param string|null $contentType
     * @param string|null $status
     * @param int|null $outdatedFlag
     * @param array $sortOptions
     * @param array|null $pageOptions
     * @param int $totalCount
     * @return array
     */
    public function searchByCondition(
        ConditionBlock $block,
        $contentType = null,
        $status = null,
        $outdatedFlag = null,
        array $sortOptions = [],
        $pageOptions = null,
        & $totalCount = 0
    ) {

        $validRequest = $block instanceof ConditionBlock &&
            $this->isValidRequest($contentType, $sortOptions, $pageOptions);

        $result = [];

        if ($validRequest) {

            if ((string)ConditionBlock::getConditionBlock() === (string)$block) {
                $block = null;
            }

            list($totalCount, $result) = $this->getTotalCountAndResult($contentType, $status, $outdatedFlag, $sortOptions, $block, $pageOptions);
        }

        return $result;
    }

    private function getTotalByFieldInValues(array $condition)
    {
        $block = ConditionBlock::getConditionBlock();

        foreach ($condition as $field => $values) {
            $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_IN, $field, $values));
        }

        $countQuery = $this->buildCountQuery(
            null,
            null,
            null,
            $block
        );

        $totalCount = $this->getDbal()->fetch($countQuery);

        // extracting from result
        $totalCount = (int)$totalCount[0]->cnt;

        return $totalCount;
    }

    public function getTotalInUploadQueue()
    {
        return $this->getTotalByFieldInValues(
            [
                SubmissionEntity::FIELD_STATUS => [
                    SubmissionEntity::SUBMISSION_STATUS_NEW,
                ],
                SubmissionEntity::FIELD_IS_LOCKED => [0],
            ]);
    }

    public function getTotalInCheckStatusHelperQueue()
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


    /**
     * @param $batchUid
     *
     * @return array
     */
    public function searchByBatchUid($batchUid)
    {
        $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, SubmissionEntity::FIELD_BATCH_UID,
            [$batchUid]);
        $block = ConditionBlock::getConditionBlock();
        $block->addCondition($condition);
        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ,
            SubmissionEntity::FIELD_STATUS, [SubmissionEntity::SUBMISSION_STATUS_NEW]));
        $total = 0;

        return $this->searchByCondition($block, null, null, null, [], null, $total);
    }

    /**
     * Gets SubmissionEntity from database by primary key
     * alias to getEntities
     *
     * @param integer $id
     *
     * @return null|SubmissionEntity[]
     */
    public function getEntityById($id)
    {
        $query = $this->buildSelectQuery([SubmissionEntity::FIELD_ID => (int)$id]);

        $obj = $this->fetchData($query);

        if (is_array($obj) && empty($obj)) {
            $obj = null;
        }

        return $obj;
    }

    /**
     * @param array $where
     * @return null|string
     */
    public function buildSelectQuery(array $where)
    {
        $whereOptions = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        foreach ($where as $key => $item) {
            $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $key, [$item]);
            $whereOptions->addCondition($condition);
        }

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName(SubmissionEntity::getTableName()),
            array_keys(SubmissionEntity::getFieldDefinitions()),
            $whereOptions,
            [],
            null
        );
        $this->logQuery($query);

        return $query;
    }

    public function buildCountQuery($contentType, $status, $outdatedFlag, ConditionBlock $baseCondition = null)
    {

        $whereOptions = null;

        if (null !== $contentType || null !== $status || null !== $outdatedFlag || null !== $baseCondition) {
            $whereOptions = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
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
            [],
            null
        );

        $this->logQuery($query);

        return $query;
    }

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @return SubmissionEntity[]
     */
    public function filterBrokenSubmissions(array $submissions)
    {
        $outArray = [];

        foreach ($submissions as $submission) {
            $submission = $this->validateSubmission($submission);

            if (SubmissionEntity::SUBMISSION_STATUS_FAILED === $submission->getStatus()) {
                continue;
            }

            $outArray[] = $submission;
        }

        return $outArray;
    }

    /**
     * @param SubmissionEntity $submission
     * @param bool             $updateState
     *
     * @return SubmissionEntity
     */
    public function validateSubmission(SubmissionEntity $submission, $updateState = true)
    {
        $blogs = $this->getEntityHelper()->getSiteHelper()->listBlogIdsFlat();

        if (!in_array($submission->getSourceBlogId(), $blogs, true)) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $submission->setLastError(vsprintf(
                'Submission has source blog = %s, expected one of: [%s]',
                [$submission->getSourceBlogId(), implode(',', $blogs)]
            ));
        } elseif (!in_array($submission->getTargetBlogId(), $blogs, true)) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $submission->setLastError(vsprintf(
                'Submission has target blog = %s, expected one of: [%s]',
                [$submission->getTargetBlogId(), implode(',', $blogs)]
            ));
        }

        if (true === $updateState) {
            $submission = $this->storeEntity($submission);
        }

        return $submission;
    }

    /**
     * Search submission by params
     *
     * @param array $params
     * @param int   $limit (if 0 - unlimited)
     *
     * @return SubmissionEntity[]
     */
    public function find(array $params = [], $limit = 0)
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
     */
    public function findSubmissionsForUploadJob()
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
     * Search submission by params.
     *
     * @param array $params
     * @param int   $limit (if 0 - unlimited)
     *
     * @return SubmissionEntity[]
     */
    public function findBatchUidNotEmpty(array $params = [], $limit = 0)
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

        $block->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_NOT_EQ,
            SubmissionEntity::FIELD_BATCH_UID, ['']));

        $pageOptions = 0 === $limit ? null : ['limit' => $limit, 'page' => 1];

        $query = $this->buildQuery(null, null, null, [], $pageOptions, $block);

        return $this->fetchData($query);
    }

    /**
     * @param int[] $ids
     *
     * @return SubmissionEntity[]
     */
    public function findByIds(array $ids)
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_IN, SubmissionEntity::FIELD_ID, $ids);
        $block->addCondition($condition);
        $query = $this->buildQuery(null, null, null, [], null, $block);

        return $this->fetchData($query);
    }

    /**
     * Builds SELECT query for Submissions
     *
     * @param string         $contentType
     * @param string         $status
     * @param                $outdatedFlag
     * @param array|null     $sortOptions
     * @param array|null     $pageOptions
     * @param ConditionBlock $baseCondition
     * @param null           $groupOptions
     *
     * @return string
     */
    private function buildQuery(
        $contentType,
        $status,
        $outdatedFlag,
        $sortOptions,
        $pageOptions,
        ConditionBlock $baseCondition = null,
        $groupOptions = null
    ) {

        $whereOptions = null;

        if (null !== $contentType || null !== $status || null !== $outdatedFlag || null !== $baseCondition) {
            $whereOptions = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
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
            $groupOptions
        );

        $this->logQuery($query);

        return $query;
    }

    /**
     * @return array
     */
    public function getColumnsLabels()
    {
        return SubmissionEntity::getFieldLabels();
    }

    /**
     * @return array
     */
    public function getSortableFields()
    {
        return SubmissionEntity::getSortableFields();
    }

    public static function getChangedFields(SubmissionEntity $submission)
    {
        return in_array($submission->getId(), [0, null], true) // Inserting submission?
            ? $submission->toArray(false)
            : $submission->getChangedFields();
    }

    /**
     * Stores SubmissionEntity to database. (fills id if needed)
     * @param SubmissionEntity $entity
     * @return SubmissionEntity
     */
    public function storeEntity(SubmissionEntity $entity)
    {
        $originalSubmission = json_encode($entity->toArray(false));
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
        }

        if (true === $is_insert && false !== $result) {
            $entityFields = $entity->toArray(false);
            $entityFields[SubmissionEntity::FIELD_ID] = $this->getDbal()->getLastInsertedId();
            // update reference to entity
            $entity = SubmissionEntity::fromArray($entityFields, $this->getLogger());
        }
        $this->getLogger()->debug(
            vsprintf('Finished saving submission: %s. id=%s', [$originalSubmission, $entity->getId()])
        );

        return $entity;
    }

    /**
     * @param array $fields
     *
     * @return SubmissionEntity
     */
    public function createSubmission(array $fields)
    {
        return SubmissionEntity::fromArray($fields, $this->getLogger());
    }

    /**
     * @param string $contentType
     * @param int $sourceBlogId
     * @param int $sourceId
     * @param int $targetBlogId
     * @return SubmissionEntity|null
     */
    public function findSubmission($contentType, $sourceBlogId, $sourceId, $targetBlogId)
    {
        $entities = $this->find([
            SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
        ]);

        if (count($entities) > 0) {
            return ArrayHelper::first($entities);
        }

        return null;
    }

    /**
     * Loads from database or creates a new instance of SubmissionEntity
     *
     * @param string                           $contentType
     * @param int                              $sourceBlog
     * @param int                              $sourceEntity
     * @param int                              $targetBlog
     * @param LocalizationPluginProxyInterface $localizationProxy
     * @param null|int                         $targetEntity
     *
     * @return SubmissionEntity
     */
    public function getSubmissionEntity(
        $contentType,
        $sourceBlog,
        $sourceEntity,
        $targetBlog,
        LocalizationPluginProxyInterface $localizationProxy,
        $targetEntity = null
    ) {
        $params = [
            SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlog,
            SubmissionEntity::FIELD_SOURCE_ID => $sourceEntity,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlog,
        ];

        if (null !== $targetEntity) {
            $params[SubmissionEntity::FIELD_TARGET_ID] = (int)$targetEntity;
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
    public function storeSubmissions(array $submissions)
    {
        $newList = [];

        foreach ($submissions as $submission) {
            $newList[] = $this->storeEntity($submission);
        }

        return $newList;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return mixed
     */
    public function delete(SubmissionEntity $submission)
    {
        $result = false;

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
                $result = $this->getDbal()->query($query);
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

        return $result;
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $message
     *
     * @return SubmissionEntity
     */
    public function setErrorMessage(SubmissionEntity $submission, $message)
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

    /**
     * @param string $contentType
     * @param string $status
     * @param string $outdatedFlag
     * @param array|null $sortOptions
     * @param ConditionBlock|null $block
     * @param array|null $pageOptions
     * @return array
     */
    private function getTotalCountAndResult($contentType, $status, $outdatedFlag, array $sortOptions = null, ConditionBlock $block = null, array $pageOptions = null)
    {
        $totalCount = $this->getDbal()->fetch($this->buildCountQuery($contentType, $status, $outdatedFlag, $block));

        return [(int)$totalCount[0]->cnt, $this->fetchData($this->buildQuery($contentType, $status, $outdatedFlag, $sortOptions, $pageOptions, $block))];
    }
}
