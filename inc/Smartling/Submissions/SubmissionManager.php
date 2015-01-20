<?php

namespace Smartling\Submissions;


use Psr\Log\LoggerInterface;
use Smartling\Helpers\ContentTypeHelper;

class SubmissionManager {

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * @var ContentTypeHelper
     */
    private $helper = null;

    public function __construct(LoggerInterface $logger, ContentTypeHelper $helper)
    {
        $this->logger = $logger;
        $this->helper = $helper;
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

    }


    public function getEntityById($id)
    {

    }

    public function storeEntity(SubmissionEntity $entity){}
}