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

    if (!function_exists('esc_html')) {
        function esc_html($a)
        {
            return htmlspecialchars($a, ENT_QUOTES);
        }
    }

    if (!function_exists('admin_url')) {
        function admin_url($path = '')
        {
            return 'http://example.com/wp-admin/' . ltrim($path, '/');
        }
    }
}

namespace Smartling\Tests\Smartling\WP\Table {

    use PHPUnit\Framework\TestCase;
    use Smartling\ApiWrapperInterface;
    use Smartling\DbAl\UploadQueueManager;
    use Smartling\Helpers\WordpressFunctionProxyHelper;
    use Smartling\Queue\QueueInterface;
    use Smartling\Settings\ConfigurationProfileEntity;
    use Smartling\Settings\Locale;
    use Smartling\Settings\SettingsManager;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;
    use Smartling\WP\Table\QueueManagerTableWidget;

    class QueueManagerTableWidgetTest extends TestCase
    {
        private function buildWidget(
            ApiWrapperInterface $api,
            int $uploadQueueCount = 1,
        ): QueueManagerTableWidget {
            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getProjectId')->willReturn('testProject');

            $sourceLocale = $this->createMock(Locale::class);
            $sourceLocale->method('getBlogId')->willReturn(1);
            $profile->method('getSourceLocale')->willReturn($sourceLocale);

            $settingsManager = $this->createMock(SettingsManager::class);
            $settingsManager->method('getActiveProfile')->willReturn($profile);

            $queue = $this->createMock(QueueInterface::class);
            $queue->method('stats')->willReturn([
                QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE => 0,
                QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE => 0,
            ]);

            $uploadQueueManager = $this->createMock(UploadQueueManager::class);
            $uploadQueueManager->method('count')->willReturn($uploadQueueCount);

            $submissionManager = $this->createMock(SubmissionManager::class);
            $submissionManager->method('getTotalInCheckStatusHelperQueue')->willReturn(0);
            $submissionManager->method('findSubmissionForCloning')->willReturn(null);

            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('get_current_blog_id')->willReturn(1);

            // Use an anonymous subclass to bypass WP_List_Table::__construct(), which
            // calls convert_to_screen() / get_current_screen() and requires a fully
            // initialised WordPress admin environment that is unavailable in unit tests.
            return new class(
                $api,
                $queue,
                $settingsManager,
                $submissionManager,
                $uploadQueueManager,
                $wpProxy,
            ) extends QueueManagerTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected ApiWrapperInterface $api,
                    protected QueueInterface $queue,
                    protected SettingsManager $settingsManager,
                    protected SubmissionManager $submissionManager,
                    protected UploadQueueManager $uploadQueueManager,
                    protected WordpressFunctionProxyHelper $wpProxy,
                ) {
                    $this->setSource([]);
                }
            };
        }

        public function testPrepareItemsDoesNotThrowWhenApiCredentialsAreInvalid(): void
        {
            $authError = new SmartlingApiException(
                [['key' => 'authentication.failed', 'message' => 'Invalid credentials']],
                401,
            );

            $api = $this->createMock(ApiWrapperInterface::class);
            $api->method('acquireLock')->willThrowException($authError);

            $widget = $this->buildWidget($api, uploadQueueCount: 3);

            $widget->prepare_items();

            $this->assertNotEmpty($widget->items);
            $uploadRow = $widget->items[0];
            $this->assertStringContainsString('API error', $uploadRow['run_cron']);
            $this->assertStringContainsString('Invalid credentials', $uploadRow['run_cron']);
        }

        public function testPrepareItemsShowsRunningMessageWhenLockHeld(): void
        {
            $lockError = new SmartlingApiException(
                [['key' => 'resource.locked', 'message' => 'Resource is locked']],
                423,
            );

            $api = $this->createMock(ApiWrapperInterface::class);
            $api->method('acquireLock')->willThrowException($lockError);

            $widget = $this->buildWidget($api, uploadQueueCount: 3);

            $widget->prepare_items();

            $uploadRow = $widget->items[0];
            $this->assertStringContainsString('Running', $uploadRow['run_cron']);
        }
    }
}
