<?php

namespace Smartling\Submissions;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
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
use Smartling\Processors\EntityProcessor;

/**
 * Class SubmissionManager
 * @package Smartling\Submissions
 */
class SubmissionManager extends EntityManagerAbstract
{
    /**
     * The table name
     */
    const SUBMISSIONS_TABLE_NAME = 'smartling_submissions';

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
     * @param LoggerInterface                              $logger
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param int                                          $pageSize
     * @param EntityHelper                                 $entityHelper
     */
    public function __construct(LoggerInterface $logger, $dbal, $pageSize, $entityHelper)
    {
        $siteHelper = $entityHelper->getSiteHelper();
        $proxy = $entityHelper->getConnector();
        parent::__construct($logger, $dbal, $pageSize, $siteHelper, $proxy);
        $this->entityHelper = $entityHelper;
    }

    /**
     * @param $contentType
     *
     * @return bool
     */
    private function validateContentType($contentType)
    {
        return
            null === $contentType
            || in_array($contentType, array_keys(WordpressContentTypeHelper::getReverseMap()), true);
    }

    protected function dbResultToEntity(array $dbRow)
    {
        return SubmissionEntity::fromArray((array)$dbRow, $this->getLogger());
    }

    /**
     * Validates request
     *
     * @param $contentType
     * @param $sortOptions
     * @param $pageOptions
     *
     * @return bool
     */
    private function validateRequest($contentType, $sortOptions, $pageOptions)
    {
        $fSortOptionsAreValid = QueryBuilder::validateSortOptions(
            array_keys(
                SubmissionEntity::getFieldDefinitions()
            ),
            $sortOptions
        );

        $fPageOptionsValid = QueryBuilder::validatePageOptions($pageOptions);

        $fContentTypeValid = $this->validateContentType($contentType);

        $validRequest = $fContentTypeValid && $fPageOptionsValid && $fSortOptionsAreValid;

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
    public function getEntities($contentType = null, $status = null, $outdatedFlag = null, array $sortOptions = [], $pageOptions = null, $targetBlogId = null, & $totalCount = 0)
    {
        $validRequest = $this->validateRequest($contentType, $sortOptions, $pageOptions);

        $result = [];

        if ($validRequest) {

            if (null !== $targetBlogId) {
                $block = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'target_blog_id', [$targetBlogId]);
                $block->addCondition($condition);
            } else {
                $block = null;
            }

            $dataQuery = $this->buildQuery($contentType, $status, $outdatedFlag, $sortOptions, $pageOptions, $block);

            $countQuery = $this->buildCountQuery($contentType, $status, $outdatedFlag, $block);

            $totalCount = $this->getDbal()->fetch($countQuery);

            // extracting from result
            $totalCount = (int)$totalCount[0]->cnt;

            $result = $this->fetchData($dataQuery);
        }

        return $result;
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
    )
    {

        $searchText = trim($searchText);

        $totalCount = 0;

        $validRequest = !empty($searchFields) && $this->validateRequest($contentType, $sortOptions, $pageOptions);

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

            $dataQuery = $this->buildQuery($contentType, $status, $outdatedFlag, $sortOptions, $pageOptions, $block);

            $countQuery = $this->buildCountQuery($contentType, $status, $outdatedFlag, $block);

            $totalCount = $this->getDbal()->fetch($countQuery);

            // extracting from result
            $totalCount = (int)$totalCount[0]->cnt;

            $result = $this->fetchData($dataQuery);
        }

        return $result;
    }

    public function searchByCondition(ConditionBlock $block, $contentType = null, $status = null, $outdatedFlag = null, array $sortOptions = [], $pageOptions = null, & $totalCount = 0)
    {

        $validRequest = $block instanceof ConditionBlock &&
                        $this->validateRequest($contentType, $sortOptions, $pageOptions);

        $result = [];

        if ($validRequest) {

            if ((string)ConditionBlock::getConditionBlock() === (string)$block) {
                $block = null;
            }

            $dataQuery = $this->buildQuery($contentType, $status, $outdatedFlag, $sortOptions, $pageOptions, $block);

            $countQuery = $this->buildCountQuery($contentType, $status, $outdatedFlag, $block);

            $totalCount = $this->getDbal()->fetch($countQuery);

            // extracting from result
            $totalCount = (int)$totalCount[0]->cnt;

            $result = $this->fetchData($dataQuery);
        }

        return $result;
    }

    /**
     * Gets SubmissionEntity from database by primary key
     * alias to getEntities
     *
     * @param integer $id
     *
     * @return null|SubmissionEntity
     */
    public function getEntityById($id)
    {
        $query = $this->buildSelectQuery(['id' => (int)$id]);

        $obj = $this->fetchData($query, false);

        if (is_array($obj) && empty($obj)) {
            $obj = null;
        }

        return $obj;
    }

    /**
     * Gets SubmissionEntity from database by primary key
     * alias to getEntities
     *
     * @param integer $sourceGuid
     *
     * @return null|SubmissionEntity
     */
    public function getEntityBySourceGuid($sourceGuid)
    {
        $query = $this->buildSelectQuery(['source_id' => $sourceGuid]);

        $obj = $this->fetchData($query, false);

        if (is_array($obj) && empty($obj)) {
            $obj = null;
        }

        return $obj;
    }

    /**
     * @return null|string
     */
    public function buildSelectQuery($where)
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
                        'content_type',
                        [$contentType]
                    )
                );
            }

            if (!is_null($status)) {
                $whereOptions->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_EQ,
                        'status',
                        [$status]
                    )
                );
            }

            if (!is_null($outdatedFlag)) {
                $whereOptions->addCondition(
                    Condition::getCondition(
                        ConditionBuilder::CONDITION_SIGN_EQ,
                        'outdated',
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
    public function find(array $params = [], $limit = 0, $group = [])
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
     * @param int[] $ids
     *
     * @return SubmissionEntity[]
     */
    public function findByIds(array $ids)
    {
        $block = new ConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_IN, 'id', $ids);
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
    )
    {

        $whereOptions = null;

        if (null !== $contentType || null !== $status || null !== $outdatedFlag || null !== $baseCondition) {
            $whereOptions = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
            if ($baseCondition instanceof ConditionBlock) {
                $whereOptions->addConditionBlock($baseCondition);
            }

            if (!is_null($contentType)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'content_type', [$contentType]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($status)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'status', [$status]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($outdatedFlag)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'outdated', [$outdatedFlag]);
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
        $id = $submission->getId();

        $is_insert = in_array($id, [0, null], true);

        $fields = true === $is_insert
            ? $submission->toArray(false)
            : $submission->getChangedFields();

        return $fields;
    }

    /**
     * Stores SubmissionEntity to database. (fills id in needed)
     *
     * @param SubmissionEntity $entity
     *
     * @return SubmissionEntity
     */
    public function storeEntity(SubmissionEntity $entity)
    {
        $originalSubmission = json_encode($entity->toArray(false));
        $this->getLogger()->debug(vsprintf('Starting saving submission: %s', [$originalSubmission]));
        $submissionId = $entity->id;

        $is_insert = in_array($submissionId, [0, null], true);

        $fields = self::getChangedFields($entity);

        foreach ($fields as $field => $value) {
            if (null === $value) {
                unset($fields[$field]);
            }
        }

        if (array_key_exists('id', $fields)) {
            unset ($fields['id']);
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
            $entityFields['id'] = $this->getDbal()->getLastInsertedId();
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
    public function getSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog, LocalizationPluginProxyInterface $localizationProxy, $targetEntity = null)
    {
        if ($sourceBlog === $targetBlog) {
            $message = vsprintf(
                'Cancelled preparing submission for contentType=%s sourceId=%s sourceBlog=%s targetBlog=%s. Source and Target blogs must differ.',
                [
                    $contentType,
                    $sourceBlog,
                    $sourceEntity,
                    $targetBlog,
                ]
            );

            $this->getLogger()->error($message);
            $this->getLogger()->error(implode(PHP_EOL, Bootstrap::Backtrace()));

            throw new \InvalidArgumentException($message);
        }
        $entity = null;

        $params = [
            'content_type'   => $contentType,
            'source_blog_id' => $sourceBlog,
            'source_id'      => $sourceEntity,
            'target_blog_id' => $targetBlog,
        ];

        if (null !== $targetEntity) {
            $params['target_id'] = (int)$targetEntity;
        }

        $entities = $this->find($params);

        if (count($entities) > 0) {
            $entity = ArrayHelper::first($entities);
            /**
             * @var SubmissionEntity $entity
             */
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
                        'id',
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

                $this->getLogger()->debug(
                    vsprintf(
                        'Executing delete query for submission id=%s',
                        [
                            $submissionId,
                        ]
                    )
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
}