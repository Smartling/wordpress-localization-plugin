<?php

namespace Smartling\Tests\Smartling;

use PHPUnit\Framework\TestCase;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Tests\Mocks\ApiWrapper;
use Smartling\Vendor\Smartling\Jobs\JobsApi;

class ApiWrapperTest extends TestCase {

    public function testFindLastJobByFileUri()
    {
        $jobs = $this->createMock(JobsApi::class);
        $jobs->method('searchJobs')->willReturn([]);
        $profile = $this->createMock(ConfigurationProfileEntity::class);
        $profile->method('getProjectId')->willReturn('test');
        $settingsManager = $this->createMock(SettingsManager::class);

        $this->assertEquals(null, (new ApiWrapper($settingsManager, $jobs))->findLastJobByFileUri($profile, 'fileUri'));

        $jobs = $this->createMock(JobsApi::class);
        $jobs->method('searchJobs')->willReturn([
            "totalCount" => 4,
            "items" => [
                [
                    "createdDate" => "2016-11-21T11:51:17Z",
                    "jobName" => "myJobName second",
                    "jobStatus" => "IN_PROGRESS",
                    "translationJobUid" => "abc123aba",
                ],
                [
                    "createdDate" => "2015-11-21T11:51:17Z",
                    "jobName" => "myJobName first",
                    "jobStatus" => "IN_PROGRESS",
                    "translationJobUid" => "abc123abb",
                ],
                [
                    "createdDate" => "2018-11-21T11:51:17Z",
                    "jobName" => "myJobName last",
                    "jobStatus" => "IN_PROGRESS",
                    "translationJobUid" => "abc123abd",
                ],
                [
                    "createdDate" => "2017-11-21T11:51:17Z",
                    "jobName" => "myJobName third",
                    "jobStatus" => "IN_PROGRESS",
                    "translationJobUid" => "abc123abc",
                ],
            ],
        ]);
        $found = (new ApiWrapper($settingsManager, $jobs))
            ->findLastJobByFileUri($this->createMock(ConfigurationProfileEntity::class), 'fileUri');
        $this->assertEquals("IN_PROGRESS", $found->getStatus());
        $this->assertEquals("myJobName last", $found->getJobInformationEntity()->getJobName());
        $this->assertEquals("abc123abd", $found->getJobInformationEntity()->getJobUid());
    }
}
