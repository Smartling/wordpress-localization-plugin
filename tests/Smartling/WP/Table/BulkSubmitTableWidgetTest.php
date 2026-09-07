<?php

namespace {
    if (!class_exists('WP_List_Table')) {
        class WP_List_Table
        {
            public function __construct($args = [])
            {
            }

            public function get_pagenum()
            {
                return 1;
            }

            public function set_pagination_args($args)
            {
            }
        }
    }

    if (!function_exists('__')) {
        function __($a)
        {
            return $a;
        }
    }
}

namespace Smartling\Tests\Smartling\WP\Table {

    use PHPUnit\Framework\TestCase;
    use Smartling\Base\SmartlingCore;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\DbAl\UploadQueueManager;
    use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Helpers\NonceVerifier;
    use Smartling\Helpers\WordpressFunctionProxyHelper;
    use Smartling\Jobs\JobEntityWithBatchUid;
    use Smartling\Models\IntegerIterator;
    use Smartling\Processors\ContentEntitiesIOFactory;
    use Smartling\Settings\ConfigurationProfileEntity;
    use Smartling\Settings\Locale;
    use Smartling\Submissions\SubmissionEntity;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
    use Smartling\WP\Table\BulkSubmitTableWidget;

    class BulkSubmitTableWidgetTest extends TestCase
    {
        protected function tearDown(): void
        {
            unset($_SERVER['REQUEST_METHOD']);
        }

        /**
         * Cloning from the Bulk Submit page was removed: the only executor for
         * isCloned=1 submissions created outside the upload queue (UploadJob::processCloning())
         * was deleted, which would have left such submissions permanently orphaned.
         * processBulkAction() must now always prepare submissions as regular
         * (non-cloned) uploads and enqueue every one of them, regardless of the
         * 'action' request value.
         */
        public function testProcessBulkActionEnqueuesEverySubmissionForUpload()
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $currentBlogId = 1;
            $projectUid = 'projectUid';
            $submissionId = 3;
            $submissionType = 'post';
            $targetBlogId = 5;

            $manager = $this->createMock(SubmissionManager::class);
            $manager->method('getPageSize')->willReturn(7);

            $contentEntitiesIOFactory = $this->createMock(ContentEntitiesIOFactory::class);
            $contentEntitiesIOFactory->method('getMapper')->willReturn($this->getMockForAbstractClass(EntityAbstract::class));

            $locale = $this->createMock(Locale::class);
            $locale->method('getBlogId')->willReturn($currentBlogId);

            $core = $this->createMock(SmartlingCore::class);
            $core->method('getContentIoFactory')->willReturn($contentEntitiesIOFactory);
            $core->expects($this->once())->method('prepareForUpload')->with($submissionType, $currentBlogId, $submissionId, $targetBlogId)->willReturnCallback(function (string $contentType, int $sourceBlog, int $sourceEntity, int $targetBlog, JobEntityWithBatchUid $jobInfo) use ($projectUid) {
                $this->assertEquals('', $jobInfo->getBatchUid());
                $jobInfo = $jobInfo->getJobInformationEntity();
                $this->assertEquals(null, $jobInfo->getId());
                $this->assertEquals('', $jobInfo->getJobName());
                $this->assertEquals('', $jobInfo->getJobUid());
                $this->assertEquals($projectUid, $jobInfo->getProjectUid());
                return (new SubmissionEntity())->setId(1);
            });

            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getSourceLocale')->willReturn($locale);
            $profile->method('getProjectId')->willReturn($projectUid);

            $uploadQueueManager = $this->createMock(UploadQueueManager::class);
            $uploadQueueManager->expects($this->once())->method('enqueue')->with(
                $this->callback(static fn(IntegerIterator $ids) => $ids->getArrayCopy() === [1]),
                '',
            );

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('wp_verify_nonce')->with('valid-nonce', BulkSubmitTableWidget::BULK_ACTION_NONCE_ACTION)->willReturn(1);

            $x = new class($this->createMock(
                LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $core,
                $manager,
                $uploadQueueManager,
                $profile,
                $wpProxy,
                new NonceVerifier($wpProxy),
            ) extends BulkSubmitTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SiteHelper $siteHelper,
                    protected SmartlingCore $core,
                    protected SubmissionManager $manager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected ConfigurationProfileEntity $profile,
                    protected WordpressFunctionProxyHelper $wpProxy,
                    protected NonceVerifier $nonceVerifier,
                ) {
                }
            };
            $x->setSource([
                'smartling-bulk-submit-page-content-type' => $submissionType,
                'smartling-bulk-submit-page-submission' => ["$submissionId-$submissionType"],
                'jobName' => '',
                'description-sm' => '',
                'dueDate' => '',
                'bulk-submit-locales' => ['locales' => [$targetBlogId => [
                    'blog' => (string)$targetBlogId,
                    'locale' => 'Test',
                    'enabled' => 'on',
                ]]],
                // Any action other than 'send' skips the (uninvolved, container-dependent)
                // batch-retrieval branch and goes straight to preparing/enqueuing
                // submissions - exactly what used to happen for the now-removed 'clone'
                // action, and is unaffected by removing it.
                'action' => 'add-to-existing-job',
                '_wpnonce' => 'valid-nonce',
            ]);
            $x->processBulkAction();
        }

        /**
         * processBulkAction() must reject a POST request when the nonce is missing or
         * invalid, without preparing or enqueueing anything, regardless of which fields
         * the request happens to carry.
         */
        public function testProcessBulkActionRejectsInvalidNonce()
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $submissionId = 3;
            $submissionType = 'post';
            $targetBlogId = 5;

            $manager = $this->createMock(SubmissionManager::class);
            $core = $this->createMock(SmartlingCore::class);
            $core->expects($this->never())->method('prepareForUpload');

            $profile = $this->createMock(ConfigurationProfileEntity::class);

            $uploadQueueManager = $this->createMock(UploadQueueManager::class);
            $uploadQueueManager->expects($this->never())->method('enqueue');

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('wp_verify_nonce')->willReturn(false);

            $x = new class($this->createMock(
                LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $core,
                $manager,
                $uploadQueueManager,
                $profile,
                $wpProxy,
                new NonceVerifier($wpProxy),
            ) extends BulkSubmitTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SiteHelper $siteHelper,
                    protected SmartlingCore $core,
                    protected SubmissionManager $manager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected ConfigurationProfileEntity $profile,
                    protected WordpressFunctionProxyHelper $wpProxy,
                    protected NonceVerifier $nonceVerifier,
                ) {
                }
            };
            $x->setSource([
                'smartling-bulk-submit-page-content-type' => $submissionType,
                'smartling-bulk-submit-page-submission' => ["$submissionId-$submissionType"],
                'bulk-submit-locales' => ['locales' => [$targetBlogId => [
                    'blog' => (string)$targetBlogId,
                    'locale' => 'Test',
                    'enabled' => 'on',
                ]]],
                'action' => 'add-to-existing-job',
                '_wpnonce' => 'not-a-valid-nonce',
            ]);
            $x->processBulkAction();
        }

        /**
         * A scalar `smartling` request value (e.g. a crafted `?smartling=x` link) must not
         * bypass the nonce guard. Before the fix, is_array($smartlingData) was false so the
         * guard was skipped entirely, yet `empty('x')` was also false, so processing fell
         * through into the 'send' branch and reached the container-dependent
         * `Bootstrap::getContainer()->get('api.wrapper.with.retries')->retrieveBatch(...)`
         * call unauthenticated. Normalizing non-array values to [] up front makes both the
         * guard and `empty($smartlingData)` agree there is no payload, so it returns before
         * ever reaching that call - which would otherwise fatal in this test, since no DI
         * container is bootstrapped here.
         */
        public function testProcessBulkActionIgnoresNonArraySmartlingPayload()
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();

            $core = $this->createMock(SmartlingCore::class);
            $core->expects($this->never())->method('prepareForUpload');

            $profile = $this->createMock(ConfigurationProfileEntity::class);

            $uploadQueueManager = $this->createMock(UploadQueueManager::class);
            $uploadQueueManager->expects($this->never())->method('enqueue');

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->expects($this->never())->method('wp_verify_nonce');

            $x = new class($this->createMock(
                LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $core,
                $this->createMock(SubmissionManager::class),
                $uploadQueueManager,
                $profile,
                $wpProxy,
                new NonceVerifier($wpProxy),
            ) extends BulkSubmitTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SiteHelper $siteHelper,
                    protected SmartlingCore $core,
                    protected SubmissionManager $manager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected ConfigurationProfileEntity $profile,
                    protected WordpressFunctionProxyHelper $wpProxy,
                    protected NonceVerifier $nonceVerifier,
                ) {
                }
            };
            $x->setSource([
                'smartling' => 'x',
                // No '_wpnonce' provided: this must not matter, because the request should
                // never be treated as carrying a bulk-action payload in the first place.
            ]);
            $x->processBulkAction();
        }

        /**
         * A scalar `bulk-submit-locales` request value must not bypass the nonce guard nor
         * reach `array_key_exists('locales', $data)`, which throws a TypeError in PHP 8 when
         * $data is not an array.
         */
        public function testProcessBulkActionIgnoresNonArrayLocalesPayload()
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();

            $core = $this->createMock(SmartlingCore::class);
            $core->expects($this->never())->method('prepareForUpload');

            $profile = $this->createMock(ConfigurationProfileEntity::class);

            $uploadQueueManager = $this->createMock(UploadQueueManager::class);
            $uploadQueueManager->expects($this->never())->method('enqueue');

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->expects($this->never())->method('wp_verify_nonce');

            $x = new class($this->createMock(
                LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $core,
                $this->createMock(SubmissionManager::class),
                $uploadQueueManager,
                $profile,
                $wpProxy,
                new NonceVerifier($wpProxy),
            ) extends BulkSubmitTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SiteHelper $siteHelper,
                    protected SmartlingCore $core,
                    protected SubmissionManager $manager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected ConfigurationProfileEntity $profile,
                    protected WordpressFunctionProxyHelper $wpProxy,
                    protected NonceVerifier $nonceVerifier,
                ) {
                }
            };
            $x->setSource([
                'action' => 'add-to-existing-job',
                'bulk-submit-locales' => 'foo',
            ]);
            $x->processBulkAction();
        }

        /**
         * A POST request must always require a valid nonce, even when it carries none of the
         * specific fields (submission, bulk-submit-locales, smartling) that the previous
         * per-field heuristic checked for. This is what actually closes the maintenance risk
         * that heuristic had: a future side-effecting field added to the form is covered
         * automatically, without needing to be remembered and added to an enumeration.
         */
        public function testProcessBulkActionRequiresNonceForAnyPostRequestRegardlessOfFields()
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();
            $_SERVER['REQUEST_METHOD'] = 'POST';

            $core = $this->createMock(SmartlingCore::class);
            $core->expects($this->never())->method('prepareForUpload');

            $profile = $this->createMock(ConfigurationProfileEntity::class);

            $uploadQueueManager = $this->createMock(UploadQueueManager::class);
            $uploadQueueManager->expects($this->never())->method('enqueue');

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->expects($this->once())->method('wp_verify_nonce')
                ->with('not-a-valid-nonce', BulkSubmitTableWidget::BULK_ACTION_NONCE_ACTION)
                ->willReturn(false);

            $x = new class($this->createMock(
                LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $core,
                $this->createMock(SubmissionManager::class),
                $uploadQueueManager,
                $profile,
                $wpProxy,
                new NonceVerifier($wpProxy),
            ) extends BulkSubmitTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SiteHelper $siteHelper,
                    protected SmartlingCore $core,
                    protected SubmissionManager $manager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected ConfigurationProfileEntity $profile,
                    protected WordpressFunctionProxyHelper $wpProxy,
                    protected NonceVerifier $nonceVerifier,
                ) {
                }
            };
            $x->setSource([
                // No 'submission', 'bulk-submit-locales', or 'smartling' field at all - exactly
                // the shape the old $hasBulkActionPayload heuristic would have let through
                // unchecked.
                '_wpnonce' => 'not-a-valid-nonce',
            ]);
            $x->processBulkAction();
        }

        /**
         * processBulkAction() reads its input from $_REQUEST (GET+POST+COOKIE - see
         * setSource($_REQUEST) in the constructor), but gating the nonce check on
         * isPostRequest() alone let a GET request carrying the exact same actionable
         * fields a POST would (locales enabled, submissions selected) skip the check
         * entirely and still enqueue submissions for translation. A crafted link or
         * <img> tag visited by a logged-in admin could trigger it: GET-based CSRF.
         */
        public function testProcessBulkActionRequiresNonceForGetRequestWithPayload()
        {
            WordpressFunctionsMockHelper::injectFunctionsMocks();
            // No $_SERVER['REQUEST_METHOD'] set to 'POST': this must not matter.
            $submissionId = 3;
            $submissionType = 'post';
            $targetBlogId = 5;

            $core = $this->createMock(SmartlingCore::class);
            $core->expects($this->never())->method('prepareForUpload');

            $profile = $this->createMock(ConfigurationProfileEntity::class);

            $uploadQueueManager = $this->createMock(UploadQueueManager::class);
            $uploadQueueManager->expects($this->never())->method('enqueue');

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->expects($this->once())->method('wp_verify_nonce')
                ->with('not-a-valid-nonce', BulkSubmitTableWidget::BULK_ACTION_NONCE_ACTION)
                ->willReturn(false);

            $x = new class($this->createMock(
                LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $core,
                $this->createMock(SubmissionManager::class),
                $uploadQueueManager,
                $profile,
                $wpProxy,
                new NonceVerifier($wpProxy),
            ) extends BulkSubmitTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SiteHelper $siteHelper,
                    protected SmartlingCore $core,
                    protected SubmissionManager $manager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected ConfigurationProfileEntity $profile,
                    protected WordpressFunctionProxyHelper $wpProxy,
                    protected NonceVerifier $nonceVerifier,
                ) {
                }
            };
            $x->setSource([
                'smartling-bulk-submit-page-content-type' => $submissionType,
                'smartling-bulk-submit-page-submission' => ["$submissionId-$submissionType"],
                'bulk-submit-locales' => ['locales' => [$targetBlogId => [
                    'blog' => (string)$targetBlogId,
                    'locale' => 'Test',
                    'enabled' => 'on',
                ]]],
                'action' => 'add-to-existing-job',
                '_wpnonce' => 'not-a-valid-nonce',
            ]);
            $x->processBulkAction();
        }
    }
}
