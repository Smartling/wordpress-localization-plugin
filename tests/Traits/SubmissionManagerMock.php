<?php

namespace Smartling\Tests\Traits;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Submissions\SubmissionManager;

trait SubmissionManagerMock
{
    /**
     * @param SmartlingToCMSDatabaseAccessWrapperInterface $dbal
     * @param EntityHelper $entityHelper
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|SubmissionManager
     */
    private function mockSubmissionManager(SmartlingToCMSDatabaseAccessWrapperInterface $dbal, EntityHelper $entityHelper)
    {
        return $this->getMockBuilder(SubmissionManager::class)
            ->setMethods(
                [
                    'find',
                    'findByIds',
                    'filterBrokenSubmissions',
                    'storeEntity',
                    'setErrorMessage',
                    'storeSubmissions',
                ]
            )
            ->setConstructorArgs([$dbal, 10, $entityHelper])
            ->getMock();
    }
}
