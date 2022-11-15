<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Jobs\JobManager;
use Smartling\Jobs\SubmissionsJobsManager;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

trait SubmissionManagerMock
{
    /**
     * @return MockObject|SubmissionManager
     */
    private function mockSubmissionManager(SmartlingToCMSDatabaseAccessWrapperInterface $dbal)
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
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(SettingsManager::class),
                $this->createMock(SiteHelper::class),
                $this->createMock(JobManager::class),
                $this->createMock(SubmissionsJobsManager::class)
            ])
            ->getMock();
    }
}
