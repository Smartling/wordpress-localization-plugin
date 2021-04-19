<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Jobs\JobInformationManager;
use Smartling\Jobs\SubmissionJobManager;
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
                $this->createMock(JobInformationManager::class),
                $this->createMock(SubmissionJobManager::class)
            ])
            ->getMock();
    }
}
