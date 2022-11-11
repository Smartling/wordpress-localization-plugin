<?php

namespace {
    if (!class_exists('WP_List_Table')) {
        class WP_List_Table
        {
            public function __construct($a)
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

    if (!function_exists('convert_to_screen')) {
        function convert_to_screen($a)
        {
            $r = new \stdClass();
            $r->id = $a;
            return $r;
        }
    }
}

namespace Smartling\Tests\Smartling\WP\Table {

    use Smartling\ApiWrapperInterface;
    use Smartling\Helpers\EntityHelper;
    use Smartling\Queue\QueueInterface;
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
            $x = $this->getMockBuilder(SubmissionTableWidget::class)->setConstructorArgs([
                $apiWrapper,
                $submissionManager,
                $this->createMock(EntityHelper::class),
                $queue,
            ]);
            // Is this running with WordPress classes?
            if (method_exists('WP_List_Table', 'current_action')) {
                $x->onlyMethods(['current_action']);
            } else {
                $x->addMethods(['current_action']);
            }
            $x = $x->getMock();
            $x->method('current_action')->willReturn(SubmissionTableWidget::ACTION_DOWNLOAD);
            $x->setSource(['submission' => array_map(static function (SubmissionEntity $submission): string {
                return (string)$submission->getId();
            }, $submissions)]);

            $x->prepare_items();
        }
    }
}
