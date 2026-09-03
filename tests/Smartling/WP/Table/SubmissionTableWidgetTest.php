<?php

namespace {
    if (!class_exists('WP_List_Table')) {
        class WP_List_Table
        {
            public function __construct($a = [])
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

    use Smartling\ApiWrapperInterface;
    use Smartling\DbAl\LocalizationPluginProxyInterface;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Helpers\WordpressFunctionProxyHelper;
    use Smartling\Queue\QueueInterface;
    use Smartling\Settings\SettingsManager;
    use Smartling\Submissions\SubmissionEntity;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\WP\Table\SubmissionTableWidget;
    use PHPUnit\Framework\TestCase;

    class SubmissionTableWidgetTest extends TestCase {
        public function testBulkSubmitDownload(): void
        {
            $submissions = [
                (new SubmissionEntity())->setId(1),
                (new SubmissionEntity())->setId(2),
                (new SubmissionEntity())->setId(3),
            ];
            $submissionManager = $this->createMock(SubmissionManager::class);
            $submissionManager->expects($this->once())->method('findByIds')->with(array_map(static function (SubmissionEntity $submission): int {
                return $submission->getId();
            }, $submissions))->willReturn($submissions);
            $submissionManager->method('getPageSize')->willReturn(50);
            $queue = $this->createMock(QueueInterface::class);
            $queue->expects($this->exactly(count($submissions)))->method('enqueue');
            $apiWrapper = $this->createMock(ApiWrapperInterface::class);
            $apiWrapper->expects($this->once())->method('createAuditLogRecord');
            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('wp_verify_nonce')->with('valid-nonce', SubmissionTableWidget::BULK_ACTION_NONCE_ACTION)->willReturn(1);
            $x = $this->buildWidget($apiWrapper, $this->createMock(SettingsManager::class), $submissionManager, $queue, $wpProxy);

            $x->setSource([
                'submission' => array_map(static function (SubmissionEntity $submission): string {
                    return (string)$submission->getId();
                }, $submissions),
                '_wpnonce' => 'valid-nonce',
            ]);

            $x->processBulkAction();
        }

        /**
         * processBulkAction() must reject a request carrying submission ids when the nonce is
         * missing or invalid, without touching any submission.
         */
        public function testProcessBulkActionRejectsInvalidNonce(): void
        {
            $submissionManager = $this->createMock(SubmissionManager::class);
            $submissionManager->expects($this->never())->method('findByIds');
            $queue = $this->createMock(QueueInterface::class);
            $queue->expects($this->never())->method('enqueue');
            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->method('wp_verify_nonce')->willReturn(false);
            $x = $this->buildWidget($this->createMock(ApiWrapperInterface::class), $this->createMock(SettingsManager::class), $submissionManager, $queue, $wpProxy);

            $x->setSource([
                'submission' => ['1', '2'],
                '_wpnonce' => 'not-a-valid-nonce',
            ]);

            $x->processBulkAction();
        }

        /**
         * A scalar `submission` request value must not bypass the nonce guard nor reach
         * array_map(), which throws a TypeError in PHP 8 when given a non-array argument.
         */
        public function testProcessBulkActionIgnoresNonArraySubmissionPayload(): void
        {
            $submissionManager = $this->createMock(SubmissionManager::class);
            $submissionManager->expects($this->never())->method('findByIds');
            $queue = $this->createMock(QueueInterface::class);
            $queue->expects($this->never())->method('enqueue');
            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
            $wpProxy->expects($this->never())->method('wp_verify_nonce');
            $x = $this->buildWidget($this->createMock(ApiWrapperInterface::class), $this->createMock(SettingsManager::class), $submissionManager, $queue, $wpProxy);

            $x->setSource(['submission' => 'x']);

            $x->processBulkAction();
        }

        private function buildWidget(
            ApiWrapperInterface $apiWrapper,
            SettingsManager $settingsManager,
            SubmissionManager $submissionManager,
            QueueInterface $queue,
            WordpressFunctionProxyHelper $wpProxy,
        ): SubmissionTableWidget {
            return new class(
                $apiWrapper,
                $this->createMock(LocalizationPluginProxyInterface::class),
                $settingsManager,
                $this->createMock(SiteHelper::class),
                $submissionManager,
                $queue,
                $wpProxy,
            ) extends SubmissionTableWidget {
                /** @noinspection PhpMissingParentConstructorInspection */
                public function __construct(
                    protected ApiWrapperInterface $apiWrapper,
                    protected LocalizationPluginProxyInterface $localizationPluginProxy,
                    protected SettingsManager $settingsManager,
                    protected SiteHelper $siteHelper,
                    protected SubmissionManager $submissionManager,
                    protected QueueInterface $queue,
                    protected WordpressFunctionProxyHelper $wpProxy,
                ) {
                }

                public function current_action()
                {
                    return SubmissionTableWidget::ACTION_DOWNLOAD;
                }
            };
        }
    }
}
