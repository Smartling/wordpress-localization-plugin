<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 20.01.2015
 * Time: 12:15
 */

namespace Smartling\DbAl;


use Psr\Log\LoggerInterface;

class SubmissionEntityAccessor {

    private $logger = null;

    private $dbAccessor = null;

    public function __construct(LoggerInterface $logger, SmartlingToCMSDatabaseAccessWrapper $db_accessor)
    {
        $this->logger = $logger;
        $this->dbAccessor = $db_accessor;

    }


}