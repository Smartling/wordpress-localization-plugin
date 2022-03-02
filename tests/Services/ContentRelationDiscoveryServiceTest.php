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
    use Smartling\ContentTypes\ContentTypeNavigationMenu;
    use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
    use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
    use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
    use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
    use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
    use Smartling\Helpers\ContentHelper;
    use Smartling\Helpers\CustomMenuContentTypeHelper;
    use Smartling\Helpers\EntityHelper;
    use Smartling\Helpers\FieldsFilterHelper;
    use Smartling\Helpers\GutenbergBlockHelper;
    use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
    use Smartling\Helpers\ShortcodeHelper;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Jobs\JobEntity;
    use Smartling\Jobs\JobEntityWithBatchUid;
    use Smartling\Jobs\JobManager;
    use Smartling\Jobs\SubmissionJobEntity;
    use Smartling\Jobs\SubmissionsJobsManager;
    use Smartling\Models\CloneRequest;
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

            $x->createSubmissions([
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

            $x->expects(self::exactly(count($sourceIds)))->method('getPostTitle')->willReturnCallback(static function ($id) use ($titlePrefix) {
                return $titlePrefix . $id;
            });

            $x->createSubmissions([
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

        public function testExistingMenuItemsGetSubmittedOnExistingMenuBulkSubmit()
        {
            $sourceBlogId = 1;
            $sourceIds = [161];
            $contentType = ContentTypeNavigationMenu::WP_CONTENT_TYPE;
            $targetBlogId = [2];
            $batchUid = 'batchUid';
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';
            $menuSubmission = $this->createMock(SubmissionEntity::class);
            $menuSubmission->method('getContentType')->willReturn(ContentTypeNavigationMenu::WP_CONTENT_TYPE);
            $menuSubmission->method('getSourceId')->willReturn($sourceIds[0]);
            $menuSubmission->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $menuSubmission->expects($this->once())->method('setBatchUid')->with($batchUid);
            /**
             * @var SubmissionEntity[]|MockObject[] $menuItemSubmissions
             */
            $menuItemSubmissions = [];
            $menuItemPosts = [];
            $unsavedSubmissionIds = $sourceIds;
            foreach ([162, 163, 164] as $menuItemId) {
                $menuItemSubmission = $this->createMock(SubmissionEntity::class);
                $menuItemSubmission->method('getContentType')->willReturn(ContentTypeNavigationMenuItem::WP_CONTENT_TYPE);
                $menuItemSubmission->method('getSourceId')->willReturn($menuItemId);
                $menuItemSubmission->expects($this->once())->method('setBatchUid')->with($batchUid);
                $menuItemSubmission->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
                $menuItemSubmissions[] = $menuItemSubmission;
                $post = new \stdClass();
                $post->ID = $menuItemId;
                $menuItemPosts[] = $post;
                $unsavedSubmissionIds[] = $menuItemId;
            }

            $this->assertCount(4, $unsavedSubmissionIds);
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

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
            $submissionManager->method('find')->willReturnCallback(function ($params) use ($menuSubmission, $menuItemSubmissions): array {
                switch ($params[SubmissionEntity::FIELD_CONTENT_TYPE]) {
                    case ContentTypeNavigationMenu::WP_CONTENT_TYPE:
                        return [$menuSubmission];
                    case ContentTypeNavigationMenuItem::WP_CONTENT_TYPE:
                        foreach ($menuItemSubmissions as $submission) {
                            if ($submission->getSourceId() === $params[SubmissionEntity::FIELD_SOURCE_ID]) {
                                return [$submission];
                            }
                        }
                        return $menuItemSubmissions;
                    default:
                        return [];
                }
            });
            $submissionManager->method('storeEntity')->willReturnCallback(function (SubmissionEntity $submission) use (&$unsavedSubmissionIds) {
                $unsavedSubmissionIds = array_filter($unsavedSubmissionIds, static function ($item) use ($submission) {
                    return $item !== $submission->getSourceId();
                });
                return $submission;
            });

            $mapper = $this->createMock(EntityAbstract::class);
            $mapper->method('get')->willReturnCallback(function ($guid) {
                $result = new PostEntityStd();
                $result->ID = $guid;
                return $result;
            });
            $contentIoFactory = $this->createMock(ContentEntitiesIOFactory::class);
            $contentIoFactory->method('getMapper')->willReturn($mapper);

            $customMenuContentTypeHelper = $this->createMock(CustomMenuContentTypeHelper::class);
            $customMenuContentTypeHelper->method('getMenuItems')->willReturn($menuItemPosts);

            $x = new ContentRelationsDiscoveryService(
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
                $customMenuContentTypeHelper,
            );

            $x->bulkUpload(new JobEntityWithBatchUid($batchUid, $jobName, $jobUid, $projectUid), $sourceIds, $contentType, $sourceBlogId, $targetBlogId);
            $this->assertCount(0, $unsavedSubmissionIds);
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

            if (function_exists('switch_to_blog')) {
                switch_to_blog(1);
            }
            $x->createSubmissions([
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

        public function testCloningNoDuplication()
        {
            $contentType = 'post';
            $cloneLevel1 = 1;
            $childPostId = 2;
            $rootPostId = 1;
            $sourceBlogId = 1;
            $targetBlogId = 2;

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);
            $submissionManager = $this->createMock(SubmissionManager::class);

            $submissionManager->expects($this->at(0))->method('find')->with([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_SOURCE_ID => $childPostId,
            ]);
            $submissionManager->expects($this->at(1))->method('find')->with([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_SOURCE_ID => $rootPostId,
            ]);

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $contentHelper,
                $this->createMock(SettingsManager::class),
                $submissionManager
            );
            $x->clone(new CloneRequest($rootPostId, $contentType, [$cloneLevel1 => [$targetBlogId => [$contentType => [$childPostId]]]], [$targetBlogId]));
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
                $this->createMock(CustomMenuContentTypeHelper::class),
            ])->onlyMethods(['getPostTitle'])->getMock();
        }
    }
}
