<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Jobs\JobInformationManager;
use Smartling\Submissions\SubmissionManager;

trait SubmissionManagerMock
{
    /**
     * @return MockObject|SubmissionManager
     */
    private function mockSubmissionManager(SmartlingToCMSDatabaseAccessWrapperInterface $dbal, EntityHelper $entityHelper, JobInformationManager $jobInformationManager = null)
    {
        if ($jobInformationManager === null) {
            $jobInformationManager = $this->createMock(JobInformationManager::class);
        }
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
            ->setConstructorArgs([$dbal, 10, $entityHelper, $jobInformationManager])
            ->getMock();
    }
}
