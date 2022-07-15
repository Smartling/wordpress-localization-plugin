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
    use Smartling\Bootstrap;
    use Smartling\ContentTypes\ContentTypeNavigationMenu;
    use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
    use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
    use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
    use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
    use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
    use Smartling\Extensions\Acf\AcfDynamicSupport;
    use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
    use Smartling\Helpers\ContentHelper;
    use Smartling\Helpers\CustomMenuContentTypeHelper;
    use Smartling\Helpers\EntityHelper;
    use Smartling\Helpers\FieldsFilterHelper;
    use Smartling\Helpers\GutenbergBlockHelper;
    use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\PostBasedProcessor;
    use Smartling\Helpers\MetaFieldProcessor\DefaultMetaFieldProcessor;
    use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorManager;
    use Smartling\Helpers\ShortcodeHelper;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Helpers\TranslationHelper;
    use Smartling\Jobs\JobEntity;
    use Smartling\Jobs\JobEntityWithBatchUid;
    use Smartling\Jobs\JobManager;
    use Smartling\Jobs\SubmissionJobEntity;
    use Smartling\Jobs\SubmissionsJobsManager;
    use Smartling\Models\GutenbergBlock;
    use Smartling\Models\UserCloneRequest;
    use Smartling\Models\UserTranslationRequest;
    use Smartling\Processors\ContentEntitiesIOFactory;
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

    class ContentRelationDiscoveryServiceTest extends TestCase
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

            $apiWrapper->expects($this->once())->method('createAuditLogRecord')->willReturnCallback(function (ConfigurationProfileEntity $configurationProfile, string $actionType, string $description, array $clientData, ?JobEntityWithBatchUid $jobInfo = null, ?bool $isAuthorize = null) use ($batchUid, $jobName, $jobUid, $profile, $sourceBlogId, $sourceId, $targetBlogId): void {
                $this->assertEquals($profile, $configurationProfile);
                $this->assertEquals(CreateRecordParameters::ACTION_TYPE_UPLOAD, $actionType);
                $this->assertEquals('From Widget', $description);
                $this->assertEquals([
                    'relatedContentIds' => [],
                    'sourceBlogId' => $sourceBlogId,
                    'sourceId' => $sourceId,
                    'targetBlogIds' => [$targetBlogId],
                ], $clientData);
                $this->assertNotNull($jobInfo);
                $this->assertEquals($batchUid, $jobInfo->getBatchUid());
                $job = $jobInfo->getJobInformationEntity();
                $this->assertEquals($jobName, $job->getJobName());
                $this->assertEquals($jobUid, $job->getJobUid());
                $this->assertEquals($profile->getProjectId(), $job->getProjectUid());
                $this->assertTrue($isAuthorize);
            });

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager);

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
            $submission->method('getId')->willReturn(48, 48, 49, 49); // getId is used two times per submission

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

            $x->expects(self::exactly(count($sourceIds)))->method('getTitle')->willReturnCallback(static function (SubmissionEntity $submission) use ($titlePrefix) {
                return $titlePrefix . $submission->getId();
            });

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
                $this->createMock(AcfDynamicSupport::class),
                $contentHelper,
                $this->createMock(FieldsFilterHelper::class),
                $this->createMock(MetaFieldProcessorManager::class),
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(AbsoluteLinkedAttachmentCoreHelper::class),
                $this->createMock(ShortcodeHelper::class),
                $this->createMock(GutenbergBlockHelper::class),
                $this->createMock(SubmissionFactory::class),
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

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager, new SubmissionFactory());

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
            $submissionManager->expects($this->once())->method('find')->willReturn([$existing]);
            $submissionManager->expects($this->once())->method('storeSubmissions')->with([]);

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $this->createMock(ContentHelper::class),
                $this->createMock(SettingsManager::class),
                $submissionManager
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

            $settingsManager = $this->createMock(SettingsManager::class);

            $fieldFilterHelper = $this->getMockBuilder(FieldsFilterHelper::class)
                ->setConstructorArgs([$settingsManager, $this->createMock(AcfDynamicSupport::class)])
                ->onlyMethods([])
                ->getMock();

            $metaFieldProcessorManager = $this->createMock(MetaFieldProcessorManager::class);
            $metaFieldProcessorManager->method('getProcessor')->willReturnCallback(function (string $fieldName) {
                if ($fieldName === 'entity/post_parent') {
                    return new PostBasedProcessor($this->createMock(TranslationHelper::class), '');
                }
                return $this->createMock(DefaultMetaFieldProcessor::class);
            });

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $contentHelper,
                $settingsManager,
                $this->createMock(SubmissionManager::class),
                null,
                $metaFieldProcessorManager, $fieldFilterHelper,
            );
            $x->method('getBackwardRelatedTaxonomies')->willReturn([]);
            $x->method('normalizeReferences')->willReturnCallback(function (array $references) {
                $result = $references;
                if (isset($references['PostBasedProcessor'])) {
                    $postTypeIds = array_keys($references['PostBasedProcessor']);
                    foreach ($postTypeIds as $postTypeId) {
                        $result['page'][] = $postTypeId;
                    }
                    unset($result['PostBasedProcessor']);
                }
                return $result;
            });

            $relations = $x->getRelations('post', 1, [$targetBlogId]);
            $this->assertEquals($parentId, $relations->getMissingReferences()[$targetBlogId]['page'][0]);
        }

        public function testRelatedItemsSentForTranslation()
        {
            $batchUid = 'batchUid';
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
            $apiWrapper->method('retrieveBatch')->willReturn($batchUid);
            $apiWrapper->expects($this->once())->method('createAuditLogRecord')->willReturnCallback(function (ConfigurationProfileEntity $configurationProfile, string $actionType, string $description, array $clientData, ?JobEntityWithBatchUid $jobInfo = null, ?bool $isAuthorize = null) use ($depth1AttachmentId, $depth2AttachmentId): void {
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

            $submissionManager = $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock();
            $submissionManager->expects(self::once())->method('find')->with([
                'source_blog_id' => $sourceBlogId,
                'target_blog_id' => $targetBlogId,
                'content_type' => $contentType,
                'source_id' => $sourceId,
            ])->willReturn([$submission]);

            // Expects the original submission and 2 related submissions are stored to DB
            $submissionManager->expects(self::exactly(3))->method('storeEntity');
            $submissionManager->expects(self::at(1))->method('storeEntity')->with($submission);

            $x = $this->getContentRelationDiscoveryService($apiWrapper, $contentHelper, $settingsManager, $submissionManager);

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
            $acfDynamicSupport->expects($this->at(0))->method('getReferencedTypeByKey')->with($attachmentKey)->willReturn(AcfDynamicSupport::REFERENCED_TYPE_MEDIA);
            $acfDynamicSupport->expects($this->at(1))->method('getReferencedTypeByKey')->with($irrelevantKey)->willReturn(AcfDynamicSupport::REFERENCED_TYPE_NONE);

            $x = $this->getContentRelationDiscoveryService(
                $this->createMock(ApiWrapper::class),
                $this->createMock(ContentHelper::class),
                $this->createMock(SettingsManager::class),
                $this->createMock(SubmissionManager::class),
                null,
                null,
                null,
                $acfDynamicSupport
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

        /**
         * @return MockObject|ContentRelationsDiscoveryService
         */
        private function getContentRelationDiscoveryService(
            ApiWrapper $apiWrapper,
            ContentHelper $contentHelper,
            SettingsManager $settingsManager,
            SubmissionManager $submissionManager,
            SubmissionFactory $submissionFactory = null,
            MetaFieldProcessorManager $metaFieldProcessorManager = null,
            FieldsFilterHelper $fieldsFilterHelper = null,
            AcfDynamicSupport $acfDynamicSupport = null
        )
        {
            if ($acfDynamicSupport === null) {
                $acfDynamicSupport = $this->createMock(AcfDynamicSupport::class);
            }
            if ($metaFieldProcessorManager === null) {
                $metaFieldProcessorManager = $this->createMock(MetaFieldProcessorManager::class);
            }
            if ($fieldsFilterHelper === null) {
                $fieldsFilterHelper = $this->createMock(FieldsFilterHelper::class);
            }
            if ($submissionFactory === null) {
                $submissionFactory = $this->createMock(SubmissionFactory::class);
            }
            return $this->getMockBuilder(ContentRelationsDiscoveryService::class)->setConstructorArgs([
                $acfDynamicSupport,
                $contentHelper,
                $fieldsFilterHelper,
                $metaFieldProcessorManager,
                $this->createMock(LocalizationPluginProxyInterface::class),
                $this->createMock(AbsoluteLinkedAttachmentCoreHelper::class),
                $this->createMock(ShortcodeHelper::class),
                $this->createMock(GutenbergBlockHelper::class),
                $submissionFactory,
                $submissionManager,
                $apiWrapper,
                $this->createMock(MediaAttachmentRulesManager::class),
                $this->createMock(ReplacerFactory::class),
                $settingsManager,
                $this->createMock(CustomMenuContentTypeHelper::class),
            ])->onlyMethods(['getTitle', 'getBackwardRelatedTaxonomies', 'normalizeReferences'])->getMock();
        }
    }
}
