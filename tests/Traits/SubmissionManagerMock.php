<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Jobs\JobManager;
use Smartling\Jobs\SubmissionsJobsManager;
use Smartling\Submissions\SubmissionManager;

trait SubmissionManagerMock
{
    /**
     * @return MockObject|SubmissionManager
     */
    private function mockSubmissionManager(SmartlingToCMSDatabaseAccessWrapperInterface $dbal, EntityHelper $entityHelper)
    {
        return $this->getMockBuilder(SubmissionManager::class)
            ->onlyMethods(
                [
                    'find',
                    'findByIds',
                    'storeEntity',
                    'setErrorMessage',
                    'storeSubmissions',
                ]
            )
            ->setConstructorArgs([
                $dbal,
                10,
                $entityHelper,
                $this->createMock(JobManager::class),
                $this->createMock(SubmissionsJobsManager::class)
            ])
            ->getMock();
    }
}
