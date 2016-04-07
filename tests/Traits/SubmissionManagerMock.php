<?php

namespace Smartling\Tests\Traits;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;

/**
 * Class SubmissionManagerMock
 * @package Smartling\Tests\Traits
 */
trait SubmissionManagerMock
{
    /**
     * @param LoggerInterface                              $logger
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param EntityHelper                                 $entityHelper
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|SubmissionManager
     */
    private function mockSubmissionManager(LoggerInterface $logger, SmartlingToCMSDatabaseAccessWrapperInterface $dbal, EntityHelper $entityHelper)
    {
        return $this->getMockBuilder('Smartling\Submissions\SubmissionManager')
            ->setMethods(['find'])
            ->setConstructorArgs([$logger, $dbal, 10, $entityHelper])
            ->getMock();
    }
}