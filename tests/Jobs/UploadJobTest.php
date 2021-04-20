<?php

namespace Smartling\Tests\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\QueryBuilder\TransactionManager;
use Smartling\Helpers\SiteHelper;
use Smartling\Jobs\JobInformationEntityWithBatchUid;
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
     * @param SubmissionManager $submissionManager
     * @param ApiWrapperInterface $apiWrapper
     * @param SettingsManager $settingsManager
     * @param TransactionManager|null $transactionManager
     * @return MockObject|UploadJob
     */
    private function getWorkerMock(SubmissionManager $submissionManager,
                                   ApiWrapperInterface $apiWrapper,
                                   SettingsManager $settingsManager,
                                   TransactionManager $transactionManager = null
    ) {
        if ($transactionManager === null) {
            $transactionManager = $this->getMockBuilder(TransactionManager::class)
                ->setConstructorArgs([$this->mockDbAl()])
                ->onlyMethods(['executeSelectForUpdate'])
                ->getMock();
        }

        return $this->getMockBuilder(UploadJob::class)
            ->setConstructorArgs([$submissionManager, 1200, $apiWrapper, $settingsManager, $transactionManager])
            ->onlyMethods([])
            ->getMock();
    }

    public function testDailyBucketJobAutoUploadProfile()
    {
        $batchUid = 'batchUid';

        $entityHelper = $this->createMock(EntityHelper::class);
        $siteHelper = $this->createMock(SiteHelper::class);
        $entityHelper->method('getSiteHelper')->willReturn($siteHelper);

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
        $api->method('retrieveJobInfoForDailyBucketJob')->willReturn(new JobInformationEntityWithBatchUid($batchUid, '', '', ''));

        $x = $this->getWorkerMock($submissionManager, $api, $settingsManager);
        $x->run();

        $this->assertEquals($batchUid, $submission->getBatchUid());
    }

    public function testDailyBucketJobMultipleProfiles()
    {
        $batchUid = 'batchUid';

        $entityHelper = $this->createMock(EntityHelper::class);
        $siteHelper = $this->createMock(SiteHelper::class);
        $entityHelper->method('getSiteHelper')->willReturn($siteHelper);

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
        $api->method('retrieveJobInfoForDailyBucketJob')->willReturn(new JobInformationEntityWithBatchUid($batchUid, '', '', ''));

        $x = $this->getWorkerMock($submissionManager, $api, $settingsManager);
        $x->run();

        $this->assertEquals('', $submission->getBatchUid());
    }
}
