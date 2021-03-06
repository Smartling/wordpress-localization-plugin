<?php

namespace {
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!function_exists('apply_filters')) {
        /** @noinspection PhpUnusedParameterInspection */
        function apply_filters($a, $b) {
            return $b;
        }
    }
}

namespace Smartling\Tests\Services {

    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use Smartling\ApiWrapper;
    use Smartling\Bootstrap;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
    use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
    use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
    use Smartling\Helpers\ContentHelper;
    use Smartling\Helpers\EntityHelper;
    use Smartling\Helpers\FieldsFilterHelper;
    use Smartling\Helpers\GutenbergBlockHelper;
    use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
    use Smartling\Helpers\ShortcodeHelper;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Jobs\JobEntity;
    use Smartling\Jobs\JobManager;
    use Smartling\Jobs\SubmissionJobEntity;
    use Smartling\Jobs\SubmissionsJobsManager;
    use Smartling\Processors\ContentEntitiesIOFactory;
    use Smartling\Replacers\ReplacerFactory;
    use Smartling\Services\ContentRelationsDiscoveryService;
    use Smartling\Settings\ConfigurationProfileEntity;
    use Smartling\Settings\SettingsManager;
    use Smartling\Submissions\SubmissionEntity;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
    use Smartling\Tuner\MediaAttachmentRulesManager;

    class ContentRelationDiscoveryServiceTest extends TestCase
    {
        protected function setUp(): void
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();
        }

        public function testCreateSubmissionsHandler()
        {
            $sourceBlogId = 1;
            $sourceId = 48;
            $contentType = 'post';
            $targetBlogId = 2;
            $batchUid = 'batchUid';
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';

            $apiWrapper = $this->createMock(ApiWrapper::class);
            $apiWrapper->method('retrieveBatch')->willReturn($batchUid);

            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn($projectUid);

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $submission = $this->createMock(SubmissionEntity::class);
            $submission->expects(self::once())->method('setBatchUid')->with($batchUid);
            $submission->expects(self::once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $submission->expects(self::never())->method('setSourceTitle');

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();

            $submissionManager->expects(self::once())->method('find')->with([
                'source_blog_id' => $sourceBlogId,
                'target_blog_id' => $targetBlogId,
                'content_type' => $contentType,
                'source_id' => $sourceId,
            ])->willReturn([$submission]);

            $submissionManager->expects(self::once())->method('storeEntity')->with($submission);

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager);

            $x->expects(self::once())->method('returnResponse')->with(['status' => 'SUCCESS']);

            $x->createSubmissionsHandler([
                'source' => ['contentType' => $contentType, 'id' => [$sourceId]],
                'job' =>
                    [
                        'id' => $jobUid,
                        'name' => $jobName,
                        'description' => '',
                        'dueDate' => '',
                        'timeZone' => 'Europe/Kiev',
                        'authorize' => 'true',
                    ],
                'targetBlogIds' => $targetBlogId,
                'relations' => [],
            ]);
        }

        public function testBulkSubmitHandler()
        {
            $sourceBlogId = 1;
            $sourceIds = [48, 49];
            $contentType = 'post';
            $targetBlogId = 2;
            $titlePrefix = 'postTitle';
            $batchUid = 'batchUid';
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';

            $apiWrapper = $this->createMock(ApiWrapper::class);
            $apiWrapper->method('retrieveBatch')->willReturn($batchUid);

            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn($projectUid);

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $expectedTitles = array_map(static function ($id) use ($titlePrefix) {
                return [$titlePrefix . $id];
            }, $sourceIds);

            $submission = $this->createMock(SubmissionEntity::class);
            $submission->expects(self::exactly(count($sourceIds)))->method('setBatchUid')->with($batchUid);
            $submission->expects(self::exactly(count($sourceIds)))->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $submission->expects(self::exactly(count($sourceIds)))->method('setSourceTitle')->withConsecutive(...$expectedTitles);

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();

            $submissionManager->expects(self::exactly(count($sourceIds)))->method('find')->withConsecutive([[
                'source_blog_id' => $sourceBlogId,
                'target_blog_id' => $targetBlogId,
                'content_type' => $contentType,
                'source_id' => $sourceIds[0],
            ]], [[
                'source_blog_id' => $sourceBlogId,
                'target_blog_id' => $targetBlogId,
                'content_type' => $contentType,
                'source_id' => $sourceIds[1],
            ]])->willReturn([$submission]);

            $submissionManager->expects(self::never())->method('getSubmissionEntity')->with([$contentType, $sourceBlogId]);

            $submissionManager->expects(self::exactly(count($sourceIds)))->method('storeEntity')->with($submission);

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager);

            $x->expects(self::once())->method('returnResponse')->with(['status' => 'SUCCESS']);
            $x->expects(self::exactly(count($sourceIds)))->method('getPostTitle')->willReturnCallback(static function ($id) use ($titlePrefix) {
                return $titlePrefix . $id;
            });

            $x->createSubmissionsHandler([
                'source' => ['contentType' => $contentType, 'id' => [0]],
                'job' =>
                    [
                        'id' => $jobUid,
                        'name' => $jobName,
                        'description' => '',
                        'dueDate' => '',
                        'timeZone' => 'Europe/Kiev',
                        'authorize' => 'true',
                    ],
                'targetBlogIds' => $targetBlogId,
                'ids' => $sourceIds,
            ]);
        }

        public function testJobInfoGetsStoredOnNewSubmissions()
        {
            $ioFactory = Bootstrap::getContainer()->get('factory.contentIO');
            $ioFactoryMock = $this->createMock(ContentEntitiesIOFactory::class);
            $ioFactoryMock->method('getMapper')->willReturn($this->createMock(WidgetEntity::class));
            Bootstrap::getContainer()->set('factory.contentIO', $ioFactoryMock);
            $sourceBlogId = 1;
            $sourceId = 48;
            $contentType = 'post';
            $targetBlogId = 2;
            $batchUid = 'batchUid';
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';
            $createdJobId = 3;

            $apiWrapper = $this->createMock(ApiWrapper::class);
            $apiWrapper->method('retrieveBatch')->willReturn($batchUid);

            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn($projectUid);

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $submissionsJobsManager = $this->createMock(SubmissionsJobsManager::class);
            $submissionsJobsManager->expects($this->once())->method('store')->willReturnCallback(function (SubmissionJobEntity $submissionJobEntity) use ($createdJobId) {
                $this->assertEquals($createdJobId, $submissionJobEntity->getJobId());
                return $submissionJobEntity;
            });

            $jobManager = $this->createMock(JobManager::class);
            $jobManager->expects($this->once())->method('store')->willReturnCallback(function(JobEntity $jobInfo) use ($createdJobId, $jobName, $jobUid, $projectUid) {
                $this->assertEquals($jobName, $jobInfo->getJobName());
                $this->assertEquals($jobUid, $jobInfo->getJobUid());
                $this->assertEquals($projectUid, $jobInfo->getProjectUid());

                return new JobEntity($jobName, $jobUid, $projectUid, $createdJobId);
            });

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->setConstructorArgs([
                $this->getMockForAbstractClass(SmartlingToCMSDatabaseAccessWrapperInterface::class),
                20,
                $this->createMock(EntityHelper::class),
                $jobManager,
                $submissionsJobsManager,
            ])->onlyMethods(['find'])->getMock();
            $submissionManager->method('find')->willReturn([]);

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager);

            $x->expects(self::once())->method('returnResponse')->with(['status' => 'SUCCESS']);

            $x->createSubmissionsHandler([
                'source' => ['contentType' => $contentType, 'id' => [$sourceId]],
                'job' =>
                    [
                        'id' => $jobUid,
                        'name' => $jobName,
                        'description' => '',
                        'dueDate' => '',
                        'timeZone' => 'Europe/Kiev',
                        'authorize' => 'true',
                    ],
                'targetBlogIds' => $targetBlogId,
                'relations' => [],
            ]);
            Bootstrap::getContainer()->set('factory.contentIO', $ioFactory);
        }

        /**
         * @return MockObject|ContentRelationsDiscoveryService
         */
        private function getContentRelationDiscoveryService(
            ApiWrapper $apiWrapper,
            ContentHelper $contentHelper,
            SettingsManager $settingsManager,
            SubmissionManager $submissionManager
        )
        {
            return $this->getMockBuilder(ContentRelationsDiscoveryService::class)->setConstructorArgs([
                $contentHelper,
                $this->createMock(FieldsFilterHelper::class),
                $this->createMock(MetaFieldProcessorManager::class),
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(AbsoluteLinkedAttachmentCoreHelper::class),
                $this->createMock(ShortcodeHelper::class),
                $this->createMock(GutenbergBlockHelper::class),
                $submissionManager,
                $apiWrapper,
                $this->createMock(MediaAttachmentRulesManager::class),
                $this->createMock(ReplacerFactory::class),
                $settingsManager,
            ])->onlyMethods(['getPostTitle', 'returnResponse'])->getMock();
        }
    }
}
