<?php

namespace {
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!function_exists('apply_filters')) {
        /** @noinspection PhpUnusedParameterInspection */
        function apply_filters($a, ...$b) {
            return $b[0];
        }
    }
}

namespace Smartling\Tests\Services {

    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use Smartling\ApiWrapper;
    use Smartling\ApiWrapperInterface;
    use Smartling\Bootstrap;
    use Smartling\ContentTypes\ContentTypeManager;
    use Smartling\ContentTypes\ContentTypeNavigationMenu;
    use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
    use Smartling\ContentTypes\ExternalContentManager;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
    use Smartling\DbAl\UploadQueueManager;
    use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
    use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
    use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
    use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
    use Smartling\Extensions\Acf\AcfDynamicSupport;
    use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
    use Smartling\Helpers\ArrayHelper;
    use Smartling\Helpers\ContentHelper;
    use Smartling\Helpers\ContentSerializationHelper;
    use Smartling\Helpers\CustomMenuContentTypeHelper;
    use Smartling\Helpers\FieldsFilterHelper;
    use Smartling\Helpers\FileUriHelper;
    use Smartling\Helpers\GutenbergBlockHelper;
    use Smartling\Helpers\GutenbergReplacementRule;
    use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\PostBasedProcessor;
    use Smartling\Helpers\MetaFieldProcessor\DefaultMetaFieldProcessor;
    use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
    use Smartling\Helpers\ShortcodeHelper;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Helpers\TranslationHelper;
    use Smartling\Helpers\WordpressFunctionProxyHelper;
    use Smartling\Jobs\JobEntity;
    use Smartling\Jobs\JobManager;
    use Smartling\Jobs\SubmissionJobEntity;
    use Smartling\Jobs\SubmissionsJobsManager;
    use Smartling\Models\GutenbergBlock;
    use Smartling\Models\UserCloneRequest;
    use Smartling\Models\UserTranslationRequest;
    use Smartling\Processors\ContentEntitiesIOFactory;
    use Smartling\Replacers\ContentIdReplacer;
    use Smartling\Replacers\ReplacerFactory;
    use Smartling\Services\ContentRelationsDiscoveryService;
    use Smartling\Services\ContentRelationsHandler;
    use Smartling\Settings\ConfigurationProfileEntity;
    use Smartling\Settings\SettingsManager;
    use Smartling\Submissions\SubmissionEntity;
    use Smartling\Submissions\SubmissionFactory;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
    use Smartling\Tuner\MediaAttachmentRulesManager;
    use Smartling\Vendor\Smartling\AuditLog\Params\CreateRecordParameters;

    class ContentRelationsDiscoveryServiceTest extends TestCase
    {
        private ?\Exception $exception = null;
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
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';

            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn($projectUid);

            $apiWrapper = $this->createMock(ApiWrapper::class);
            $apiWrapper->expects($this->once())->method('createAuditLogRecord')->willReturnCallback(function (ConfigurationProfileEntity $configurationProfile, string $actionType, string $description, array $clientData, JobEntity $job, ?bool $isAuthorize = null) use ($jobName, $jobUid, $profile, $sourceBlogId, $sourceId, $targetBlogId): void {
                $this->assertEquals($profile, $configurationProfile);
                $this->assertEquals(CreateRecordParameters::ACTION_TYPE_UPLOAD, $actionType);
                $this->assertEquals('From Widget', $description);
                $this->assertEquals([
                    'relatedContentIds' => [],
                    'sourceBlogId' => $sourceBlogId,
                    'sourceId' => $sourceId,
                    'targetBlogIds' => [$targetBlogId],
                ], $clientData);
                $this->assertEquals($jobName, $job->getJobName());
                $this->assertEquals($jobUid, $job->getJobUid());
                $this->assertEquals($profile->getProjectId(), $job->getProjectUid());
                $this->assertTrue($isAuthorize);
            });

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $submission = $this->createMock(SubmissionEntity::class);
            $submission->expects(self::once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $submission->expects(self::never())->method('setSourceTitle');
            $submission->method('getId')->willReturn(17);

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();

            $submissionManager->expects(self::once())->method('findOne')->with([
                'source_blog_id' => $sourceBlogId,
                'target_blog_id' => $targetBlogId,
                'content_type' => $contentType,
                'source_id' => $sourceId,
            ])->willReturn($submission);

            $submissionManager->expects(self::once())->method('storeEntity')->with($submission)->willReturnArgument(0);

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn($sourceBlogId);

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager, wpProxy: $wpProxy);

            $x->createSubmissions(UserTranslationRequest::fromArray([
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
            ]));
        }

        public function testCreateSubmissionsRelations()
        {
            $sourceBlogId = 1;
            $sourceId = 48;
            $contentType = 'post';
            $targetBlogId = 2;
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';

            $apiWrapper = $this->createMock(ApiWrapper::class);
            $apiWrapper->expects($this->once())->method('createBatch')->willReturnCallback(
                function (ConfigurationProfileEntity $profile, string $jobUid, array $fileUris): string {
                    $arrayPointer = 0; // TODO replace with array_is_list() once php 8.1 is used
                    foreach ($fileUris as $key => $value) {
                        if ($key !== $arrayPointer++) {
                            throw new \RuntimeException('FileUris expected to be list');
                        }
                    }

                    return '';
            });

            $submission1 = $this->createMock(SubmissionEntity::class);
            $submission1->method('getId')->willReturn(17);
            $submission1->method('getFileUri')->willReturn('17');

            $submission2 = $this->createMock(SubmissionEntity::class);
            $submission2->method('getId')->willReturn(23);
            $submission2->method('getFileUri')->willReturn('23');

            $submissionFactory = $this->createMock(SubmissionFactory::class);
            $submissionFactory->method('fromArray')->willReturn($submission1, $submission2);

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()
                ->getMock();
            $submissionManager->method('findOne')->willReturn($submission1, $submission1, $submission2);
            $submissionManager->method('storeEntity')->willReturnArgument(0);

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn($sourceBlogId);

            $this->getContentRelationDiscoveryService(
                $apiWrapper,
                submissionManager: $submissionManager,
                submissionFactory: $submissionFactory,
                wpProxy: $wpProxy
            )->createSubmissions(UserTranslationRequest::fromArray([
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
                'relations' => [
                    1 => [$targetBlogId => ['post' => [17]]],
                    2 => [$targetBlogId => ['attachment' => [23]]],
                ],
            ]));
        }
        public function testBulkSubmitHandler()
        {
            $sourceBlogId = 1;
            $sourceIds = [48, 49];
            $contentType = 'post';
            $targetBlogId = 2;
            $titlePrefix = 'postTitle';
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';

            $apiWrapper = $this->createMock(ApiWrapper::class);

            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn($projectUid);

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $expectedTitles = array_map(static function ($id) use ($titlePrefix) {
                return $titlePrefix . $id;
            }, $sourceIds);

            $submission1 = $this->createMock(SubmissionEntity::class);
            $submission1->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $submission1->expects($this->never())->method('setSourceTitle');
            $submission1->method('getId')->willReturn(48);

            $submission2 = $this->createMock(SubmissionEntity::class);
            $submission2->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $submission2->expects($this->never())->method('setSourceTitle');
            $submission2->method('getId')->willReturn(49);

            $submissions = [$submission1, $submission2];

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();

            $matcherFind = $this->exactly(count($sourceIds));
            $submissionManager->expects($matcherFind)->method('findTargetBlogSubmission')
                ->willReturnCallback(function ($callContentType, $callSourceBlogId, $callContentId, $callTargetBlogId)
                use ($contentType, $matcherFind, $sourceBlogId, $sourceIds, $submissions, $targetBlogId) {
                    $index = $matcherFind->getInvocationCount() - 1;
                    $this->assertEquals($sourceBlogId, $callSourceBlogId);
                    $this->assertEquals($targetBlogId, $callTargetBlogId);
                    $this->assertEquals($contentType, $callContentType);
                    $this->assertEquals($sourceIds[$index], $callContentId);

                    return $submissions[$index];
                });

            $submissionManager->expects(self::never())->method('getSubmissionEntity');

            $matcherStore = $this->exactly(count($sourceIds));
            $submissionManager->expects($matcherStore)->method('storeEntity')->willReturnCallback(function ($submission) use ($matcherStore, $submissions) {
                $this->assertEquals($submissions[$matcherStore->getInvocationCount() - 1], $submission);

                return $submission;
            });

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn($sourceBlogId);

            $x = $this->getContentRelationDiscoveryService(
                $apiWrapper,
                $contentHelper,
                $settingsManager,
                $submissionManager,
                wpProxy: $wpProxy,
            );

            $x->expects($this->never())->method('getTitle');

            $x->createSubmissions(UserTranslationRequest::fromArray([
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
            ]));
        }

        public function testExistingMenuItemsGetSubmittedOnExistingMenuBulkSubmit()
        {
            $sourceBlogId = 1;
            $sourceIds = [161];
            $contentType = ContentTypeNavigationMenu::WP_CONTENT_TYPE;
            $targetBlogId = [2];
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';
            $menuSubmission = $this->createMock(SubmissionEntity::class);
            $menuSubmission->method('getContentType')->willReturn(ContentTypeNavigationMenu::WP_CONTENT_TYPE);
            $menuSubmission->method('getSourceId')->willReturn($sourceIds[0]);
            $menuSubmission->method('getId')->willReturn($sourceIds[0]);
            $menuSubmission->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
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
                $menuItemSubmission->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_NEW);
                $menuItemSubmission->method('getId')->willReturn($menuItemId);
                $menuItemSubmissions[] = $menuItemSubmission;
                $post = new \stdClass();
                $post->ID = $menuItemId;
                $menuItemPosts[] = $post;
                $unsavedSubmissionIds[] = $menuItemId;
            }

            $this->assertCount(4, $unsavedSubmissionIds);
            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn($projectUid);

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
            $submissionManager->method('findTargetBlogSubmission')->willReturnCallback(
                function ($contentType, $sourceBlogId, $contentId) use ($menuSubmission, $menuItemSubmissions) {
                    switch ($contentType) {
                        case ContentTypeNavigationMenu::WP_CONTENT_TYPE:
                            return $menuSubmission;
                        case ContentTypeNavigationMenuItem::WP_CONTENT_TYPE:
                            foreach ($menuItemSubmissions as $submission) {
                                if ($submission->getSourceId() === $contentId) {
                                    return $submission;
                                }
                            }
                            break;
                    }
                    return null;
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

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn($sourceBlogId);

            $x = new ContentRelationsDiscoveryService(
                $this->createMock(AcfDynamicSupport::class),
                new ArrayHelper(),
                $contentHelper,
                $this->createMock(ContentTypeManager::class),
                $this->createMock(FieldsFilterHelper::class),
                $this->createMock(FileUriHelper::class),
                $this->createMock(MetaFieldProcessorManager::class),
                $this->createMock(UploadQueueManager::class),
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(AbsoluteLinkedAttachmentCoreHelper::class),
                $this->createMock(ShortcodeHelper::class),
                $this->createMock(GutenbergBlockHelper::class),
                $this->createMock(SubmissionFactory::class),
                $submissionManager,
                $this->createMock(ApiWrapper::class),
                $this->createMock(MediaAttachmentRulesManager::class),
                $this->createMock(ReplacerFactory::class),
                $settingsManager,
                $customMenuContentTypeHelper,
                $this->createMock(ExternalContentManager::class),
                $wpProxy,
            );

            $x->bulkUpload(true, $sourceIds, $contentType, $sourceBlogId, new JobEntity($jobName, $jobUid, $projectUid), $profile, $targetBlogId);
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
            $jobName = 'Job Name';
            $jobUid = 'abcdef123456';
            $projectUid = 'projectUid';
            $createdJobId = 3;

            $apiWrapper = $this->createMock(ApiWrapper::class);

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
                $jobManager,
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $submissionsJobsManager,
            ])->onlyMethods(['find'])->getMock();
            $submissionManager->method('find')->willReturn([]);

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn($sourceBlogId);

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager, new SubmissionFactory(), wpProxy: $wpProxy);

            $x->createSubmissions(UserTranslationRequest::fromArray([
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
            ]));
            Bootstrap::getContainer()->set('factory.contentIO', $ioFactory);
        }

        public function testCloningNoDuplication()
        {
            $serviceId = 'factory.contentIO';
            $containerBuilder = Bootstrap::getContainer();
            $io = $containerBuilder->get($serviceId);
            $factory = $this->createMock(ContentEntitiesIOFactory::class);
            $factory->method('getMapper')->willReturn($this->createMock(VirtualEntityAbstract::class));
            $containerBuilder->set($serviceId, $factory);

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

            $matcher = $this->exactly(2);
            $submissionManager->expects($matcher)->method('findTargetBlogSubmission')->willReturnCallback(function ($actualContentType, $actualSourceBlogId, $contentId, $actualTargetBlogId) use ($contentType, $childPostId, $rootPostId, $sourceBlogId, $targetBlogId, $matcher) {
                $this->assertEquals($contentType, $actualContentType);
                $this->assertEquals($sourceBlogId, $actualSourceBlogId);
                $this->assertEquals($targetBlogId, $actualTargetBlogId);
                switch ($matcher->getInvocationCount()) {
                    case 1:
                        $this->assertEquals($childPostId, $contentId);
                        break;
                    case 2:
                        $this->assertEquals($rootPostId, $contentId);
                        break;
                }
            });

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn($sourceBlogId);

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $contentHelper,
                $this->createMock(SettingsManager::class),
                $submissionManager,
                wpProxy: $wpProxy,
            );
            $x->clone(new UserCloneRequest($rootPostId, $contentType, [$cloneLevel1 => [$targetBlogId => [$contentType => [$childPostId]]]], [$targetBlogId]));

            $containerBuilder->set($serviceId, $io);
        }

        public function testCloningSkipsLockedSubmissions()
        {
            $contentType = 'post';
            $contentId = 1;
            $targetBlogId = 2;
            $existing = $this->createMock(SubmissionEntity::class);
            $existing->method('isLocked')->willReturn(true);
            $existing->expects($this->never())->method('setStatus');

            $submissionManager = $this->createMock(SubmissionManager::class);
            $submissionManager->expects($this->once())->method('findTargetBlogSubmission')->willReturn($existing);
            $submissionManager->expects($this->once())->method('storeSubmissions')->with([]);

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn(1);

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $this->createMock(ContentHelper::class),
                $this->createMock(SettingsManager::class),
                $submissionManager,
                wpProxy: $wpProxy,
            );
            $x->clone(new UserCloneRequest($contentId, $contentType, [], [$targetBlogId]));
        }

        public function testParentPageReferenceDetected()
        {
            $parentId = 10001;
            $targetBlogId = 2;
            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('checkEntityExists')->willReturn(true);

            $mapper = $this->createMock(EntityAbstract::class);
            $mapper->method('get')->willReturnCallback(function ($guid) use ($parentId) {
                $result = new PostEntityStd();
                $result->ID = $guid;
                $result->post_content = 'post content';
                $result->post_parent = $parentId;
                return $result;
            });

            $contentIoFactory = $this->createMock(ContentEntitiesIOFactory::class);
            $contentIoFactory->method('getMapper')->willReturn($mapper);

            $contentHelper->method('getIoFactory')->willReturn($contentIoFactory);

            $contentTypeManager = $this->createMock(ContentTypeManager::class);
            $contentTypeManager->method('getRegisteredContentTypes')->willReturn(['page']);

            $settingsManager = $this->createMock(SettingsManager::class);

            $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wordpressProxy->method('get_current_blog_id')->willReturn(1);
            $wordpressProxy->method('get_post_type')->willReturn('page');


            $fieldFilterHelper = new FieldsFilterHelper(
                $this->createMock(AcfDynamicSupport::class),
                $this->createMock(ContentSerializationHelper::class),
                $settingsManager,
                $wordpressProxy,
            );

            $metaFieldProcessorManager = $this->createMock(MetaFieldProcessorManager::class);
            $metaFieldProcessorManager->method('getProcessor')->willReturnCallback(function (string $fieldName) {
                if ($fieldName === 'entity/post_parent') {
                    return new PostBasedProcessor($this->createMock(SubmissionManager::class), $this->createMock( TranslationHelper::class), '');
                }
                return $this->createMock(DefaultMetaFieldProcessor::class);
            });


            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $contentHelper,
                $settingsManager,
                $this->createMock(SubmissionManager::class),
                null,
                $metaFieldProcessorManager,
                $fieldFilterHelper,
                null,
                $wordpressProxy,
                $contentTypeManager,
            );
            $x->method('getBackwardRelatedTaxonomies')->willReturn([]);

            $relations = $x->getRelations('post', 1, [$targetBlogId]);
            $this->assertEquals($parentId, $relations->getMissingReferences()[$targetBlogId]['page'][0]);
        }

        public function testRelatedItemsSentForTranslation()
        {
            $contentType = 'post';
            $jobAuthorize = 'false';
            $jobDescription = 'Test Job Description';
            $jobDueDate = '2022-02-20 20:02';
            $jobName = 'Test Job Name';
            $jobTimeZone = 'Europe/Kyiv';
            $jobUid = 'jobUid';
            $projectUid = 'projectUid';
            $sourceBlogId = 1;
            $sourceId = 48;
            $depth1AttachmentId = 3;
            $depth2AttachmentId = 5;
            $targetBlogId = 2;

            $apiWrapper = $this->createMock(ApiWrapper::class);
            $apiWrapper->expects($this->once())->method('createAuditLogRecord')->willReturnCallback(function (ConfigurationProfileEntity $configurationProfile, string $actionType, string $description, array $clientData) use ($depth1AttachmentId, $depth2AttachmentId): void {
                try { // ApiWrapper call is wrapped in try/catch, this is needed to ensure exception gets thrown in test
                    $this->assertEquals([
                        'attachment' => [$depth2AttachmentId],
                        'post' => [$depth1AttachmentId],
                    ], $clientData['relatedContentIds']);
                } catch (\Exception $e) {
                    $this->exception = $e;
                }
            });

            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn($projectUid);

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getSingleSettingsProfile')->willReturn($profile);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn($sourceBlogId);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $submission = $this->createMock(SubmissionEntity::class);
            $submission->method('getId')->willReturn(3);

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
            $submissionManager->expects(self::once())->method('findOne')->with([
                'source_blog_id' => $sourceBlogId,
                'target_blog_id' => $targetBlogId,
                'content_type' => $contentType,
                'source_id' => $sourceId,
            ])->willReturn($submission);

            // Expects the original submission and 2 related submissions are stored to DB
            $matcher = $this->exactly(3);
            $submissionManager->expects($matcher)->method('storeEntity')
                ->willReturnCallback(function (SubmissionEntity $argument) use ($matcher, $submission) {
                    if ($matcher->getInvocationCount() === 1) {
                        $this->assertEquals($submission, $argument);
                    }

                    return $argument;
                });

            $factorySubmission = $this->createMock(SubmissionEntity::class);
            $factorySubmission->method('getId')->willReturn(11);

            $submissionFactory = $this->createMock(SubmissionFactory::class);
            $submissionFactory->method('fromArray')->willReturn($factorySubmission);

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn($sourceBlogId);

            $x = $this->getContentRelationDiscoveryService(
                $apiWrapper,
                $contentHelper,
                $settingsManager,
                $submissionManager,
                submissionFactory: $submissionFactory,
                wpProxy: $wpProxy,
            );

            $x->createSubmissions(UserTranslationRequest::fromArray([
                'job' => [
                    'id' => $jobUid,
                    'name' => $jobName,
                    'description' => $jobDescription,
                    'dueDate' => $jobDueDate,
                    'timeZone' => $jobTimeZone,
                    'authorize' => $jobAuthorize,
                ],
                'formAction' => ContentRelationsHandler::FORM_ACTION_UPLOAD,
                'source' => ['id' => [$sourceId], 'contentType' => $contentType],
                'relations' => [
                    1 => [$targetBlogId => ['post' => [$depth1AttachmentId]]],
                    2 => [$targetBlogId => ['attachment' => [$depth2AttachmentId]]],
                ],
                'targetBlogIds' => (string)$targetBlogId,
            ]));
            if ($this->exception !== null) {
                throw $this->exception;
            }
        }

        public function testAcfReferencesDetected() {
            $attachmentId = 19320;
            $attachmentKey = 'field_5d5db4987e813';
            $irrelevantKey = 'field_5d5db4597e812';

            $acfDynamicSupport = $this->createMock(AcfDynamicSupport::class);
            $matcher = $this->exactly(2);
            $acfDynamicSupport->expects($matcher)->method('getReferencedTypeByKey')
                ->willReturnCallback(function ($key) use ($attachmentKey, $irrelevantKey, $matcher) {
                    switch ($matcher->getInvocationCount()) {
                        case 1:
                            $this->assertEquals($attachmentKey, $key);

                            return AcfDynamicSupport::REFERENCED_TYPE_MEDIA;
                        case 2:
                            $this->assertEquals($irrelevantKey, $key);

                            return AcfDynamicSupport::REFERENCED_TYPE_NONE;
                    }
                    $this->fail('Expected two calls');
                });

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $this->createMock(ContentHelper::class),
                $this->createMock(SettingsManager::class),
                $this->createMock(SubmissionManager::class),
                acfDynamicSupport: $acfDynamicSupport,
            );

            $this->assertEquals([$attachmentId], $x->getReferencesFromAcf(new GutenbergBlock('acf/gallery-carousel', [
                'id' => 'block_62a079bcb49b1',
                'name' => 'acf/gallery-carousel',
                'data' => [
                    'media_0_image' => $attachmentId,
                    '_media_0_image' => $attachmentKey,
                    'media' => 1,
                    '_media' => $irrelevantKey,
                ]
            ], [], '', [])));
        }

        public function testAcfReferencesArraysDetected() {
            $acfDynamicSupport = $this->createMock(AcfDynamicSupport::class);
            $acfDynamicSupport->expects($this->once())->method('getReferencedTypeByKey')
                ->willReturn(AcfDynamicSupport::REFERENCED_TYPE_POST);

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $this->createMock(ContentHelper::class),
                $this->createMock(SettingsManager::class),
                $this->createMock(SubmissionManager::class),
                acfDynamicSupport: $acfDynamicSupport,
            );

            $this->assertEquals([22892, 22893], $x->getReferencesFromAcf(new GutenbergBlock('acf/gallery-carousel', [
                'name' => 'acf/testimonial-slider',
                'data' => [
                    'testimonials_slider' => ["22892", "22893"],
                    '_testimonials_slider' => 'field_66c32d42be1aa',
                ]
            ], [], '', [])));
        }

        public function testGetRelations()
        {
            $contentType = 'post';
            $postParentId = 9024;
            $relatedPostId = 13;
            $fieldsFilterHelper = $this->createPartialMock(FieldsFilterHelper::class, []);

            $mapper = $this->createMock(EntityAbstract::class);
            $mapper->method('get')->willReturn($mapper);
            $mapper->method('toArray')->willReturn([
                'post_content' => "<!-- wp:test {\"relatedContent\":$relatedPostId} /-->",
                'post_parent' => $postParentId,
            ]);

            $metaFieldProcessorManager = $this->createMock(MetaFieldProcessorManager::class);
            $metaFieldProcessorManager->method('getProcessor')->willReturnCallback(function ($field) {
                if ($field === 'entity/post_parent') {
                    return $this->getMockBuilder(PostBasedProcessor::class)
                        ->disableOriginalConstructor()
                        ->setMockClassName(ContentRelationsDiscoveryService::POST_BASED_PROCESSOR)
                        ->getMock();
                }

                return $this->createMock(DefaultMetaFieldProcessor::class);
            });

            $ioFactory = $this->createMock(ContentEntitiesIOFactory::class);
            $ioFactory->method('getMapper')->willReturn($mapper);

            $siteHelper = $this->createMock(SiteHelper::class);
            $siteHelper->method('getCurrentBlogId')->willReturn(1);

            $contentHelper = $this->createMock(ContentHelper::class);
            $contentHelper->method('checkEntityExists')->willReturn(true);
            $contentHelper->method('getIoFactory')->willReturn($ioFactory);
            $contentHelper->method('getSiteHelper')->willReturn($siteHelper);

            $gutenbergBlockHelper = $this->createMock(GutenbergBlockHelper::class);
            $gutenbergBlockHelper->method('parseBlocks')->willReturn([
                new GutenbergBlock('test', ['relatedContent' => $relatedPostId], [], '', []),
            ]);
            $gutenbergBlockHelper->method('getAttributeValue')->willReturn($relatedPostId);

            $mediaAttachmentRulesManager = $this->createMock(MediaAttachmentRulesManager::class);
            $mediaAttachmentRulesManager->method('getGutenbergReplacementRules')->willReturn([
                new GutenbergReplacementRule('test', '$.relatedContent', ReplacerFactory::REPLACER_RELATED),
            ]);

            $replacerFactory = $this->createMock(ReplacerFactory::class);
            $replacerFactory->method('getReplacer')->willReturn($this->createMock(ContentIdReplacer::class));

            $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wordpressProxy->method('get_current_blog_id')->willReturn(1);
            $wordpressProxy->method('get_post_type')->willReturn($contentType);

            $x = $this->getContentRelationDiscoveryService(
                contentHelper: $contentHelper,
                metaFieldProcessorManager: $metaFieldProcessorManager,
                fieldsFilterHelper: $fieldsFilterHelper,
                wpProxy: $wordpressProxy,
                gutenbergBlockHelper: $gutenbergBlockHelper,
                mediaAttachmentRulesManager: $mediaAttachmentRulesManager,
                replacerFactory: $replacerFactory,
            );
            $relations = $x->getRelations($contentType, 1, [2]);
            $this->assertEquals([
                $contentType => [
                    $postParentId,
                    13,
                ]
            ], $relations->getOriginalReferences());
        }

        /**
         * @return MockObject|ContentRelationsDiscoveryService
         */
        private function getContentRelationDiscoveryService(
            ApiWrapperInterface $apiWrapper = null,
            ContentHelper $contentHelper = null,
            SettingsManager $settingsManager = null,
            SubmissionManager $submissionManager = null,
            SubmissionFactory $submissionFactory = null,
            MetaFieldProcessorManager $metaFieldProcessorManager = null,
            FieldsFilterHelper $fieldsFilterHelper = null,
            AcfDynamicSupport $acfDynamicSupport = null,
            WordpressFunctionProxyHelper $wpProxy = null,
            ContentTypeManager $contentTypeManager = null,
            GutenbergBlockHelper $gutenbergBlockHelper = null,
            MediaAttachmentRulesManager $mediaAttachmentRulesManager = null,
            ReplacerFactory $replacerFactory = null,
        )
        {
            if ($apiWrapper === null) {
                $apiWrapper = $this->createMock(ApiWrapperInterface::class);
            }
            if ($acfDynamicSupport === null) {
                $acfDynamicSupport = $this->createMock(AcfDynamicSupport::class);
            }
            if ($contentHelper === null) {
                $contentHelper = $this->createMock(ContentHelper::class);
            }
            if ($contentTypeManager === null) {
                $contentTypeManager = $this->createMock(ContentTypeManager::class);
            }
            if ($fieldsFilterHelper === null) {
                $fieldsFilterHelper = $this->createMock(FieldsFilterHelper::class);
            }
            if ($gutenbergBlockHelper === null) {
                $gutenbergBlockHelper = $this->createMock(GutenbergBlockHelper::class);
            }
            if ($mediaAttachmentRulesManager === null) {
                $mediaAttachmentRulesManager = $this->createMock(MediaAttachmentRulesManager::class);
            }
            if ($metaFieldProcessorManager === null) {
                $metaFieldProcessorManager = $this->createMock(MetaFieldProcessorManager::class);
            }
            if ($replacerFactory === null) {
                $replacerFactory = $this->createMock(ReplacerFactory::class);
            }
            if ($settingsManager === null) {
                $settingsManager = $this->createMock(SettingsManager::class);
            }
            if ($submissionFactory === null) {
                $submissionFactory = $this->createMock(SubmissionFactory::class);
            }
            if ($submissionManager === null) {
                $submissionManager = $this->createMock(SubmissionManager::class);
            }
            if ($wpProxy === null) {
                $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            }

            return $this->getMockBuilder(ContentRelationsDiscoveryService::class)->setConstructorArgs([
                $acfDynamicSupport,
                new ArrayHelper(),
                $contentHelper,
                $contentTypeManager,
                $fieldsFilterHelper,
                $this->createMock(FileUriHelper::class),
                $metaFieldProcessorManager,
                $this->createMock(UploadQueueManager::class),
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(AbsoluteLinkedAttachmentCoreHelper::class),
                $this->createMock(ShortcodeHelper::class),
                $gutenbergBlockHelper,
                $submissionFactory,
                $submissionManager,
                $apiWrapper,
                $mediaAttachmentRulesManager,
                $replacerFactory,
                $settingsManager,
                $this->createMock(CustomMenuContentTypeHelper::class),
                $this->createMock(ExternalContentManager::class),
                $wpProxy,
            ])->onlyMethods(['getTitle', 'getBackwardRelatedTaxonomies'])->getMock();
        }
    }
}
