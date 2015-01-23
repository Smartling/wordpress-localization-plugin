<?php

namespace Smartling\Submissions;


use Psr\Log\LoggerInterface;
use Smartling\DbAl\EntityManagerAbstract;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapper;
use Smartling\Helpers\ContentTypeHelper;

class SubmissionManager extends EntityManagerAbstract {

    const SUBMISSIONS_TABLE_NAME = '_smartling_submissions';

    const SUBMISSION_STATUS_NOT_TRANSLATED  = 'Not Translated';
    const SUBMISSION_STATUS_NEW             = 'New';
    const SUBMISSION_STATUS_IN_PROGRESS     = 'In Progress';
    const SUBMISSION_STATUS_TRANSLATED      = 'Translated';
    const SUBMISSION_STATUS_FAILED          = 'Failed';

    private $submissionStatuses = array(
        self::SUBMISSION_STATUS_NOT_TRANSLATED,
        self::SUBMISSION_STATUS_NEW,
        self::SUBMISSION_STATUS_IN_PROGRESS,
        self::SUBMISSION_STATUS_TRANSLATED,
        self::SUBMISSION_STATUS_FAILED,
    );

    /**
     * @return array
     */
    public function getSubmissionStatuses()
    {
        return $this->submissionStatuses;
    }

    /**
     * @return string
     */
    public function getDefaultSubmissionStatus()
    {
        return self::SUBMISSION_STATUS_IN_PROGRESS;
    }

    /**
     * @var ContentTypeHelper
     */
    private $helper = null;

    /**
     * @return ContentTypeHelper
     */
    public function getHelper()
    {
        return $this->helper;
    }

    /**
     * @var int
     */
    private $pageSize;

    /**
     * @return int
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }

    /**
     * @param LoggerInterface $logger
     * @param SmartlingToCMSDatabaseAccessWrapper $dbal
     * @param ContentTypeHelper $helper
     */
    public function __construct(
        LoggerInterface $logger,
        SmartlingToCMSDatabaseAccessWrapper $dbal,
        ContentTypeHelper $helper,
        $pageSize)
    {
        parent::__construct($logger, $dbal);
        $this->helper = $helper;
        $this->pageSize = (int) $pageSize;
    }

    private function validateContentType($contentType)
    {
        return is_null($contentType) || in_array($contentType, array_keys($this->helper->getReverseMap()));
    }

    /**
     * @param $query
     * @param bool $alwaysArray
     * @return array|SubmissionEntity
     */
    private function fetchData($query, $alwaysArray = true)
    {
        $results = array();

        $res = $this->dbal->fetch($query);

        if (is_array($res)){
            foreach($res as $row)
            {
                $results[] = SubmissionEntity::fromArray((array)$row, $this->logger, $this->helper);
            }
        }

        if (false === $alwaysArray && count($results) == 1) {
            $results = reset($results);
        }

        return $results;
    }

    private function validateRequest($contentType, $sortOptions, $pageOptions)
    {
        $fSortOptionsAreValid = $this->validateSortOptions(
            $sortOptions,
            array_keys(
                SubmissionEntity::$fieldsDefinition
            )
        );

        $fPageOptionsValid = $this->validatePageOptions($pageOptions);

        $fContentTypeValid = $this->validateContentType($contentType);

        $validRequest = $fContentTypeValid && $fPageOptionsValid && $fSortOptionsAreValid;

        return ($validRequest === true);
    }

    /**
     * @param null $contentType
     * @param null $status
     * @param $sortOptions
     * @param $pageOptions
     *
     * @param $totalCount
     * @return array of SubmissionEntity or empty array
     *
     * $sortOptions is an array that keys are SubmissionEntity fields and values are 'ASC' or 'DESC'
     * or null if no sorting needed
     *
     * e.g.: array('submissionDate' => 'ASC', 'targetLocale' => 'DESC')
     *
     * $pageOptions is an array that has keys('page' and 'limit') for pagination output purposes purposes
     * or null if no pagination needed
     *
     * e.g.: array('limit' => 20, 'page' => 1)
     */
    public function getEntities($contentType = null, $status = null, $sortOptions = null, $pageOptions = null, & $totalCount)
    {
        $totalCount = 0;

        $validRequest = $this->validateRequest($contentType, $sortOptions, $pageOptions);

        $result = array();

        if ($validRequest) {
            $query = $this->buildQuery($contentType, $status, $sortOptions, $pageOptions);

            $totalCount = $this->dbal->query($this->buildCountQuery($contentType, $status, $sortOptions));

            $result = $this->fetchData($query);

        }

        return $result;
    }

    public function search($searchText)
    {

    }

    /**
     * Gets SubmissionEntity from database by primary key
     * alias to getEntities
     * @param integer $id
     * @return null|SubmissionEntity
     */
    public function getEntityById($id)
    {
        $query = $this->buildSelectQuery(
            self::SUBMISSIONS_TABLE_NAME,
            array_keys(SubmissionEntity::$fieldsDefinition),
            array('id' => (int) $id),
            null,
            null
        );

        $obj = $this->fetchData($query, false);

        if (is_array($obj) && empty($obj)) {
            $obj = null;
        }

        return $obj;
    }

    public function buildCountQuery($contentType, $status, $sortOptions)
    {
        $whereOptions = array();

        if (!is_null($contentType)) {
            $whereOptions['contentType'] = $contentType;
        }

        if (!is_null($status)) {
            $whereOptions['status'] = $this->dbal->escape($status);
        }

        $query = $this->buildSelectQuery(
            self::SUBMISSIONS_TABLE_NAME,
            array_keys(SubmissionEntity::$fieldsDefinition),
            $whereOptions,
            $sortOptions,
            null
        );

        $this->logger->info($query);
        return $query;
    }

    /**
     * Builds SELECT query for Submissions
     * @param string $contentType
     * @param string $status
     * @param array|null $sortOptions
     * @param array|null $pageOptions
     * @return string
     */
    private function buildQuery($contentType, $status, $sortOptions, $pageOptions)
    {
        $whereOptions = array();

        if (!is_null($contentType)) {
            $whereOptions['contentType'] = $contentType;
        }

        if (!is_null($status)) {
            $whereOptions['status'] = $this->dbal->escape($status);
        }

        $query = $this->buildSelectQuery(
            self::SUBMISSIONS_TABLE_NAME,
            array_keys(SubmissionEntity::$fieldsDefinition),
            $whereOptions,
            $sortOptions,
            $pageOptions
        );

        $this->logger->info($query);
        return $query;
    }

    public function getColumnsLabels()
    {
        return SubmissionEntity::$fieldsLabels;
    }

    public function getSortableFields()
    {
        return SubmissionEntity::$fieldsSortable;
    }

    public function storeEntity(SubmissionEntity $entity){}
}