<?php

namespace Smartling\Submissions;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\EntityManagerAbstract;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
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
 *
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
            is_null($contentType)
            || in_array($contentType, array_keys(WordpressContentTypeHelper::getReverseMap()));
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
     * @param array      $sortOptions
     * @param null|array $pageOptions
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
    public function getEntities($contentType = null, $status = null, $outdatedFlag = null, array $sortOptions = [], $pageOptions = null, & $totalCount = 0)
    {
        $validRequest = $this->validateRequest($contentType, $sortOptions, $pageOptions);

        $result = [];

        if ($validRequest) {
            $dataQuery = $this->buildQuery($contentType, $status, $outdatedFlag, $sortOptions, $pageOptions);

            $countQuery = $this->buildCountQuery($contentType, $status, $outdatedFlag);

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

            $dataQuery = $this->buildQuery($contentType, $status, $sortOptions, $pageOptions, $block);

            $countQuery = $this->buildCountQuery($contentType, $status, $block);

            $totalCount = $this->getDbal()
                               ->fetch($countQuery);

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
            $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, $key,
                [$item]);
            $whereOptions->addCondition($condition);
        }

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()
                 ->completeTableName(SubmissionEntity::getTableName()),
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

        if (!is_null($contentType) || !is_null($status) || !is_null($outdatedFlag)) {
            $whereOptions = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
            if ($baseCondition instanceof ConditionBlock) {
                $whereOptions->addConditionBlock($baseCondition);
            }

            if (!is_null($contentType)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'content_type',
                    [$contentType]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($status)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'status',
                    [$status]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($outdatedFlag)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'outdated',
                                                     [$outdatedFlag]);
                $whereOptions->addCondition($condition);
            }
        }

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()
                 ->completeTableName(SubmissionEntity::getTableName()),
            [['COUNT(*)' => 'cnt']],
            $whereOptions,
            [],
            null
        );

        $this->logQuery($query);

        return $query;
    }

    /**
     * Search submission by params
     *
     * @param array $params
     *
     * @return SubmissionEntity[]
     */
    public function find(array $params = [])
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

        $query = $this->buildQuery(null, null, null, [], null, $block);

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
        $query = $this->buildQuery(null, null, [], null, $block);

        return $this->fetchData($query);
    }

    /**
     * Builds SELECT query for Submissions
     *
     * @param string         $contentType
     * @param string         $status
     * @param array|null     $sortOptions
     * @param array|null     $pageOptions
     * @param ConditionBlock $baseCondition
     *
     * @return string
     */
    private function buildQuery(
        $contentType,
        $status,
        $outdatedFlag,
        $sortOptions,
        $pageOptions,
        ConditionBlock $baseCondition = null
    )
    {

        $whereOptions = null;

        if (!is_null($contentType) || !is_null($status) || !is_null($outdatedFlag)) {
            $whereOptions = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
            if ($baseCondition instanceof ConditionBlock) {
                $whereOptions->addConditionBlock($baseCondition);
            }

            if (!is_null($contentType)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'content_type',
                    [$contentType]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($status)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'status',
                    [$status]);
                $whereOptions->addCondition($condition);
            }

            if (!is_null($outdatedFlag)) {
                $condition = Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'outdated',
                                                     [$outdatedFlag]);
                $whereOptions->addCondition($condition);
            }
        } else {
            $whereOptions = $baseCondition;
        }

        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()
                 ->completeTableName(SubmissionEntity::getTableName()),
            array_keys(SubmissionEntity::getFieldDefinitions()),
            $whereOptions,
            $sortOptions,
            $pageOptions
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

    /**
     * Stores SubmissionEntity to database. (fills id in needed)
     *
     * @param SubmissionEntity $entity
     *
     * @return SubmissionEntity
     */
    public function storeEntity(SubmissionEntity $entity)
    {
        $entityId = $entity->id;

        $is_insert = in_array($entityId, [0, null], true);

        $fields = $entity->toArray(false);

        foreach ($fields as $field => $value) {
            if (null === $value) {
                unset($fields[$field]);
            }
        }

        unset ($fields['id']);

        if ($is_insert) {
            $storeQuery = QueryBuilder::buildInsertQuery($this->getDbal()
                                                             ->completeTableName(SubmissionEntity::getTableName()),
                                                         $fields);
        } else {
            // update
            $conditionBlock = ConditionBlock::getConditionBlock();
            $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id',
                                                                  [$entityId]));
            $storeQuery = QueryBuilder::buildUpdateQuery($this->getDbal()
                                                             ->completeTableName(SubmissionEntity::getTableName()),
                                                         $fields, $conditionBlock, ['limit' => 1]);
        }

        // log store query before execution
        $this->logQuery($storeQuery);

        $result = $this->getDbal()
                       ->query($storeQuery);

        if (false === $result) {
            $message = vsprintf('Failed saving submission entity to database with following error message: %s',
                [$this->getDbal()
                      ->getLastErrorMessage()]);

            $this->getLogger()
                 ->error($message);
        }

        if (true === $is_insert && false !== $result) {
            $entityFields = $entity->toArray(false);
            $entityFields['id'] = $this->getDbal()
                                       ->getLastInsertedId();
            // update reference to entity
            $entity = SubmissionEntity::fromArray($entityFields, $this->getLogger());
        }

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
        if ($sourceBlog===$targetBlog)
        {
            $message = vsprintf(
                'Cancelled preparing submission for contentType=%s sourceId=%s sourceBlog=%s targetBlog=%s. Source and Target blogs must differ.',
                [
                    $contentType,
                    $sourceBlog,
                    $sourceEntity,
                    $targetBlog
                ]
            );
            
            $this->getLogger()->error($message);
            $this->getLogger()->error(implode(PHP_EOL,Bootstrap::Backtrace()));

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
            $entity = reset($entities);
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
}