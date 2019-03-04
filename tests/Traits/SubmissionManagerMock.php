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
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param EntityHelper                                 $entityHelper
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|SubmissionManager
     */
    private function mockSubmissionManager(SmartlingToCMSDatabaseAccessWrapperInterface $dbal, EntityHelper $entityHelper)
    {
        return $this->getMockBuilder('Smartling\Submissions\SubmissionManager')
            ->setMethods(
                [
                    'find',
                    'findByIds',
                    'filterBrokenSubmissions',
                    'storeEntity'
                ]
            )
            ->setConstructorArgs([$dbal, 10, $entityHelper])
            ->getMock();
    }
}