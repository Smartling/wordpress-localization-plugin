<?php

namespace Smartling\Tests\Jobs;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\QueryBuilder\TransactionManager;
use Smartling\Helpers\SiteHelper;
use Smartling\Jobs\LastModifiedCheckJob;
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
     * @return \PHPUnit_Framework_MockObject_MockObject|LastModifiedCheckJob
     */
    private function getWorkerMock(SubmissionManager $submissionManager,
                                   ApiWrapperInterface $apiWrapper,
                                   SettingsManager $settingsManager,
                                   TransactionManager $transactionManager = null
    ) {
        if ($transactionManager === null) {
            $transactionManager = $this->getMockBuilder(TransactionManager::class)
                ->setConstructorArgs([$this->mockDbAl()])
                ->setMethods(['executeSelectForUpdate'])
                ->getMock();
        }

        return $this->getMockBuilder(UploadJob::class)
            ->setConstructorArgs([$submissionManager, 1200, $apiWrapper, $settingsManager, $transactionManager])
            ->setMethods(null)
            ->getMock();
    }

    public function testDailyBucketJobAutoUploadProfile()
    {
        $batchUid = 'batchUid';

        $entityHelper = $this->getMock(EntityHelper::class);
        $siteHelper = $this->getMock(SiteHelper::class);
        $entityHelper->method('getSiteHelper')->willReturn($siteHelper);

        $activeProfile = new ConfigurationProfileEntity();
        $activeProfile->setUploadOnUpdate(ConfigurationProfileEntity::UPLOAD_ON_CHANGE_AUTO);
        $mainLocale = new Locale();
        $mainLocale->setBlogId(1);
        $activeProfile->setOriginalBlogId($mainLocale);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)
            ->setConstructorArgs([
                $this->mockDbAl(),
                10,
                $siteHelper,
                $this->getMock(LocalizationPluginProxyInterface::class),
            ])
            ->setMethods(['getActiveProfiles'])
            ->getMock();
        $settingsManager->method('getActiveProfiles')->willReturn([$activeProfile]);

        $submission = new SubmissionEntity();

        $submissionManager = $this->getMockBuilder(SubmissionManager::class)
            ->setConstructorArgs([
                $this->mockDbAl(),
                10,
                $entityHelper,
            ])
            ->setMethods([
                'find',
                'findSubmissionsForUploadJob',
                'storeSubmissions',
            ])
            ->getMock();

        $submissionManager->method('findSubmissionsForUploadJob')->willReturn([]);
        $submissionManager->method('find')->willReturn([
            $submission
        ]);
        $submissionManager->expects(self::once())->method('storeSubmissions')->with([$submission]);

        $api = $this->getMock(ApiWrapperInterface::class);
        $api->method('retrieveBatchForBucketJob')->willReturn($batchUid);

        $x = $this->getWorkerMock($submissionManager, $api, $settingsManager);
        $x->run();

        $this->assertEquals($batchUid, $submission->getBatchUid());
    }

    public function testDailyBucketJobMultipleProfiles()
    {
        $batchUid = 'batchUid';

        $entityHelper = $this->getMock(EntityHelper::class);
        $siteHelper = $this->getMock(SiteHelper::class);
        $entityHelper->method('getSiteHelper')->willReturn($siteHelper);

        $activeProfile = new ConfigurationProfileEntity();
        $activeProfile->setUploadOnUpdate(ConfigurationProfileEntity::UPLOAD_ON_CHANGE_MANUAL);

        $inactiveProfile = new ConfigurationProfileEntity();
        $inactiveProfile->setUploadOnUpdate(ConfigurationProfileEntity::UPLOAD_ON_CHANGE_AUTO);

        $mainLocale = new Locale();
        $mainLocale->setBlogId(1);
        $activeProfile->setOriginalBlogId($mainLocale);

        $settingsManager = $this->getMockBuilder(SettingsManager::class)
            ->setConstructorArgs([
                $this->mockDbAl(),
                10,
                $siteHelper,
                $this->getMock(LocalizationPluginProxyInterface::class),
            ])
            ->setMethods(['getActiveProfiles'])
            ->getMock();
        $settingsManager->method('getActiveProfiles')->willReturn([$activeProfile]);

        $submission = new SubmissionEntity();

        $submissionManager = $this->getMockBuilder(SubmissionManager::class)
            ->setConstructorArgs([
                $this->mockDbAl(),
                10,
                $entityHelper,
            ])
            ->setMethods([
                'find',
                'findSubmissionsForUploadJob',
                'storeSubmissions',
            ])
            ->getMock();

        $submissionManager->method('findSubmissionsForUploadJob')->willReturn([]);
        $submissionManager->method('find')->willReturn([
            $submission
        ]);
        $submissionManager->expects(self::never())->method('storeSubmissions');

        $api = $this->getMock(ApiWrapperInterface::class);
        $api->method('retrieveBatchForBucketJob')->willReturn($batchUid);

        $x = $this->getWorkerMock($submissionManager, $api, $settingsManager);
        $x->run();

        $this->assertNull($submission->getBatchUid());
    }
}