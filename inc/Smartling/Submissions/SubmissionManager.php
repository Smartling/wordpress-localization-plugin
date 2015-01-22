<?php

namespace Smartling\Submissions;


use Psr\Log\LoggerInterface;
use Smartling\DbAl\EntityManagerAbstract;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapper;
use Smartling\Helpers\ContentTypeHelper;

class SubmissionManager extends EntityManagerAbstract {

    const SUBMISSIONS_TABLE_NAME = '_smartling_submissions';

    /**
     * @var ContentTypeHelper
     */
    private $helper = null;

    /**
     * @param LoggerInterface $logger
     * @param SmartlingToCMSDatabaseAccessWrapper $dbal
     * @param ContentTypeHelper $helper
     */
    public function __construct(
        LoggerInterface $logger,
        SmartlingToCMSDatabaseAccessWrapper $dbal,
        ContentTypeHelper $helper)
    {
        parent::__construct($logger, $dbal);
        $this->helper = $helper;
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
    public function getEntities($contentType = null, $status = null, $sortOptions = null, $pageOptions = null)
    {
        $validRequest = $this->validateRequest($contentType, $sortOptions, $pageOptions);

        $result = array();

        if ($validRequest) {
            $query = $this->buildQuery($contentType, $status, $sortOptions, $pageOptions);

            $result = $this->fetchData($query);
        }

        return $result;
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



    public function storeEntity(SubmissionEntity $entity){}
}