<?php

namespace Smartling\WP\Table;

use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\LastModifiedCheckJob;
use Smartling\Jobs\SubmissionCollectorJob;
use Smartling\Jobs\UploadJob;
use Smartling\Queue\QueueInterface;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\ConfigurationProfilesController;
use Smartling\WP\Controller\SmartlingListTable;
use Smartling\WP\WPHookInterface;

class QueueManagerTableWidget extends SmartlingListTable implements WPHookInterface
{
    public function register(): void
    {
    }

    public function __construct(
        private QueueInterface $queue,
        private SubmissionManager $submissionManager,
        private UploadQueueManager $uploadQueueManager,
    )
    {
        $this->setSource($_REQUEST);
        parent::__construct([
                                'singular' => __('Queue'),
                                'plural'   => __('Queues'),
                                'ajax'     => false,
                            ]);
    }

    public function get_columns(): array
    {
        return [
            'cron_name'   => __('Cron Name'),
            'run_cron'    => __('Trigger Cron'),
            'queue_name'  => __('Queue Name'),
            'queue_purge' => __('Purge Queue'),
        ];
    }

    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }


    public function prepare_items(): void
    {
        $curStats = $this->queue->stats();
        $newSubmissionsCount = $this->uploadQueueManager->count();
        $collectorQueueSize = $this->submissionManager->getTotalInCheckStatusHelperQueue();
        $checkStatusPoolSize = $curStats[QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE] ?? 0;
        $downloadPoolSize = $curStats[QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE] ?? 0;

        $data = [
            [
                'cron_name'   => __('Upload'),
                'run_cron'    => 0 === $newSubmissionsCount
                    && $this->submissionManager->findSubmissionForCloning() === null
                    ? __('Nothing to do')
                    : vsprintf(
                        '%s (%s submissions waiting)',
                        [
                            HtmlTagGeneratorHelper::tag(
                                'a',
                                __('Process'),
                                [
                                    'href'  => vsprintf(
                                        '%s?action=cnq&_c_action=%s&argument=%s',
                                        [
                                            admin_url('admin-post.php'),
                                            ConfigurationProfilesController::ACTION_QUEUE_FORCE,
                                            UploadJob::JOB_HOOK_NAME,
                                        ]
                                    ),
                                    'class' => 'ajaxcall',
                                ]
                            ),
                            $newSubmissionsCount,
                        ]
                    ),
                'queue_name'  => __('&nbsp;'),
                'queue_purge' => 0 === $newSubmissionsCount
                    ? __('Nothing to purge')
                    : HtmlTagGeneratorHelper::tag(
                        'a',
                        __('Purge'),
                        [
                            'href' => '#',
                            'onCLick'  => sprintf(
                                "if (window.confirm('This will cancel upload for %d submissions. Upload purge is generally meant for accidentally added content that is never intended for translation. Reverting cancelled submission status is possible from Translation Progress screen.')) {fetch('%s').then(document.location.reload())} return false;",
                                $newSubmissionsCount,
                                sprintf(
                                    admin_url('admin-post.php') . '?action=cnq&_c_action=%s&argument=%s',
                                    ConfigurationProfilesController::ACTION_QUEUE_PURGE,
                                    QueueInterface::UPLOAD_QUEUE,
                                ),
                            ),
                            'style' => 'font-weight: bold',
                        ]
                    ),
            ],
            [
                'cron_name'   => __('Check Status Helper'),
                'run_cron'    => 0 === $collectorQueueSize
                    ? __('Nothing to do')
                    : vsprintf(
                        '%s (%s submissions)',
                        [
                            HtmlTagGeneratorHelper::tag(
                                'a',
                                __('Process'),
                                [
                                    'href'  => vsprintf(
                                        '%s?action=cnq&_c_action=%s&argument=%s',
                                        [
                                            admin_url('admin-post.php'),
                                            ConfigurationProfilesController::ACTION_QUEUE_FORCE,
                                            SubmissionCollectorJob::JOB_HOOK_NAME,
                                        ]
                                    ),
                                    'class' => 'ajaxcall',
                                ]
                            ),
                            $collectorQueueSize,
                        ]
                    ),
                'queue_name'  => __('&nbsp;'),
                'queue_purge' => __('&nbsp;'),
            ],
            [
                'cron_name'   => __('Check Status'),
                'run_cron'    => 0 === $checkStatusPoolSize
                    ? __('Nothing to do')
                    : vsprintf(
                        '%s (%s submissions waiting)',
                        [
                            HtmlTagGeneratorHelper::tag(
                                'a',
                                __('Process'),
                                [
                                    'href'  => vsprintf(
                                        '%s?action=cnq&_c_action=%s&argument=%s',
                                        [
                                            admin_url('admin-post.php'),
                                            ConfigurationProfilesController::ACTION_QUEUE_FORCE,
                                            LastModifiedCheckJob::JOB_HOOK_NAME,
                                        ]
                                    ),
                                    'class' => 'ajaxcall',
                                ]
                            ),
                            $checkStatusPoolSize,
                        ]
                    ),
                'queue_name' => QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE,
                'queue_purge' => $this->queuePurgeLink($checkStatusPoolSize === 0, QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE),
            ],
            [
                'cron_name'   => __('Download'),
                'run_cron'    => 0 === $downloadPoolSize
                    ? __('Nothing to do')
                    : vsprintf(
                        '%s (%s submissions waiting)',
                        [
                            HtmlTagGeneratorHelper::tag(
                                'a',
                                __('Process'),
                                [
                                    'href'  => vsprintf(
                                        '%s?action=cnq&_c_action=%s&argument=%s',
                                        [
                                            admin_url('admin-post.php'),
                                            ConfigurationProfilesController::ACTION_QUEUE_FORCE,
                                            DownloadTranslationJob::JOB_HOOK_NAME,
                                        ]
                                    ),
                                    'class' => 'ajaxcall',
                                ]
                            ),
                            $downloadPoolSize,
                        ]
                    ),
                'queue_name' => QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE,
                'queue_purge' => $this->queuePurgeLink($downloadPoolSize === 0, QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE),
            ],
        ];

        if (($curStats[QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE] ?? 0) > 0) {
            $queueName = QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE;
            $data[] = [
                'cron_name' => __('Manual Check Status'),
                'run_cron' => sprintf(
                    '%s (%s submissions waiting)',
                    HtmlTagGeneratorHelper::tag(
                        'a',
                        __('Process'),
                        [
                            'href' => sprintf(
                                '%s?action=cnq&_c_action=%s&argument=%s',
                                admin_url('admin-post.php'),
                                ConfigurationProfilesController::ACTION_QUEUE_FORCE,
                                LastModifiedCheckJob::JOB_HOOK_NAME,
                            ),
                            'class' => 'ajaxcall',
                        ]
                    ),
                    $curStats[$queueName],
                ),
                'queue_name' => $queueName,
                'queue_purge' => $this->queuePurgeLink(false, $queueName)
            ];
        }

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    private function queuePurgeLink(bool $isQueueEmpty, string $queueName): string
    {
        return $isQueueEmpty
            ? __('Nothing to purge')
            : HtmlTagGeneratorHelper::tag(
                'a',
                __('Purge'),
                [
                    'href' => sprintf(admin_url('admin-post.php') . '?action=cnq&_c_action=%s&argument=%s', ConfigurationProfilesController::ACTION_QUEUE_PURGE, $queueName),
                    'class' => 'ajaxcall',
                ]
            );
    }
}
