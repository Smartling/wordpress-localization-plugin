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

            $x = new class($this->createMock(
                LocalizationPluginProxyInterface::class),
                $this->createMock(SiteHelper::class),
                $core,
                $manager,
                $uploadQueueManager,
                $profile,
            ) extends BulkSubmitTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SiteHelper $siteHelper,
                    protected SmartlingCore $core,
                    protected SubmissionManager $manager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected ConfigurationProfileEntity $profile,
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
            ]);
            $x->processBulkAction();
        }
    }
}
