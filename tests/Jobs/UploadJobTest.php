<?php

namespace Smartling\Tests\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Jobs\UploadJob;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\DbAlMock;

class UploadJobTest extends TestCase
{
    use DbAlMock;

    /**
     * @return MockObject|UploadJob
     */
    private function getWorkerMock(SubmissionManager $submissionManager,
                                   ApiWrapperInterface $apiWrapper,
                                   SettingsManager $settingsManager
    ) {
        return $this->getMockBuilder(UploadJob::class)
            ->setConstructorArgs([$apiWrapper, $settingsManager, $submissionManager, '5m', 1200])
            ->onlyMethods([])
            ->getMock();
    }

    public function testDailyBucketJobAutoUploadProfile()
    {
        $batchUid = 'batchUid';

        $siteHelper = $this->createMock(SiteHelper::class);

        $activeProfile = $this->createMock(ConfigurationProfileEntity::class);
        $activeProfile->method('getUploadOnUpdate')->willReturn(ConfigurationProfileEntity::UPLOAD_ON_CHANGE_AUTO);
        $mainLocale = new Locale();
        $mainLocale->setBlogId(1);
        $activeProfile->method('getOriginalBlogId')->willReturn($mainLocale);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)
            ->setConstructorArgs([
                $this->mockDbAl(),
                10,
                $siteHelper,
                $this->createMock(LocalizationPluginProxyInterface::class),
            ])
            ->onlyMethods(['getActiveProfiles'])
            ->getMock();
        $settingsManager->method('getActiveProfiles')->willReturn([$activeProfile]);

        $submission = new SubmissionEntity();

        $submissionManager = $this->createMock(SubmissionManager::class);

        $submissionManager->method('findSubmissionsForUploadJob')->willReturn([]);
        $submissionManager->method('find')->willReturn([
            $submission
        ]);
        $submissionManager->expects(self::once())->method('storeSubmissions')->with([$submission]);

        $api = $this->createMock(ApiWrapperInterface::class);
        $api->method('retrieveJobInfoForDailyBucketJob')->willReturn(new JobEntityWithBatchUid($batchUid, '', '', ''));

        $x = $this->getWorkerMock($submissionManager, $api, $settingsManager);
        $x->run();

        $this->assertEquals($batchUid, $submission->getBatchUid());
    }

    public function testDailyBucketJobMultipleProfiles()
    {
        $batchUid = 'batchUid';

        $siteHelper = $this->createMock(SiteHelper::class);

        $activeProfile = $this->createMock(ConfigurationProfileEntity::class);
        $activeProfile->method('getUploadOnUpdate')->willReturn(ConfigurationProfileEntity::UPLOAD_ON_CHANGE_MANUAL);

        $mainLocale = new Locale();
        $mainLocale->setBlogId(1);
        $activeProfile->method('getOriginalBlogId')->willReturn($mainLocale);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)
            ->setConstructorArgs([
                $this->mockDbAl(),
                10,
                $siteHelper,
                $this->createMock(LocalizationPluginProxyInterface::class),
            ])
            ->onlyMethods(['getActiveProfiles'])
            ->getMock();
        $settingsManager->method('getActiveProfiles')->willReturn([$activeProfile]);

        $submission = new SubmissionEntity();

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('findSubmissionsForUploadJob')->willReturn([]);
        $submissionManager->method('find')->willReturn([
            $submission
        ]);
        $submissionManager->expects(self::never())->method('storeSubmissions');

        $api = $this->createMock(ApiWrapperInterface::class);
        $api->method('retrieveJobInfoForDailyBucketJob')->willReturn(new JobEntityWithBatchUid($batchUid, '', '', ''));

        $x = $this->getWorkerMock($submissionManager, $api, $settingsManager);
        $x->run();

        $this->assertEquals('', $submission->getBatchUid());
    }
}
