<?php

namespace Smartling\WP\Table;

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\LastModifiedCheckJob;
use Smartling\Jobs\SubmissionCollectorJob;
use Smartling\Jobs\UploadJob;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\ConfigurationProfilesController;
use Smartling\WP\Controller\SmartlingListTable;
use Smartling\WP\WPHookInterface;

/**
 * Class QueueManagerTableWidget
 * @package Smartling\WP\Table
 */
class QueueManagerTableWidget extends SmartlingListTable implements WPHookInterface
{


    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param Queue $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }


    /**
     * @inheritdoc
     */
    public function register()
    {
        //add_action('admin_post_smartling_run_cron', [$this, 'runCron']);
    }

    public function __construct(SubmissionManager $submissionManager)
    {
        $this->setSubmissionManager($submissionManager);
        $this->setSource($_REQUEST);
        parent::__construct([
                                'singular' => __('Queue'),
                                'plural'   => __('Queues'),
                                'ajax'     => false,

                            ]);
    }

    public function runCronJob()
    {

    }

    public function clearQueue()
    {
    }

    public function get_columns()
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
        switch ($column_name) {
            default:
                return $item[$column_name];
        }
    }


    public function prepare_items()
    {
        $curStats = $this->getQueue()->stats();

        $newSubmissionsCount = $this->getSubmissionManager()->getTotalInUploadQueue();

        $collectorQueueSize = $this->getSubmissionManager()->getTotalInCheckStatusHelperQueue();

        $checkStatusPoolSize = array_key_exists(Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE, $curStats)
            ? $curStats[Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE]
            : 0;

        $downloadPoolSize = array_key_exists(Queue::QUEUE_NAME_DOWNLOAD_QUEUE, $curStats)
            ? $curStats[Queue::QUEUE_NAME_DOWNLOAD_QUEUE]
            : 0;

        $data = [
            [
                'cron_name'   => __('Upload'),
                'run_cron'    => 0 === $newSubmissionsCount
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
                'queue_purge' => __('&nbsp;'),
            ],
            [
                'cron_name'   => __('Check Status Helper'),
                'run_cron'    => 0 === $collectorQueueSize
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
                'queue_name'  => Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE,
                'queue_purge' => 0 === $checkStatusPoolSize
                    ? __('Nothing to purge')
                    : HtmlTagGeneratorHelper::tag(
                        'a',
                        __('Purge'),
                        [
                            'href'  => vsprintf(
                                admin_url('admin-post.php') . '?action=cnq&_c_action=%s&argument=%s',
                                [
                                    ConfigurationProfilesController::ACTION_QUEUE_PURGE,
                                    Queue::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE,
                                ]
                            ),
                            'class' => 'ajaxcall',
                        ]
                    ),
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
                'queue_name'  => Queue::QUEUE_NAME_DOWNLOAD_QUEUE,
                'queue_purge' => 0 === $downloadPoolSize
                    ? __('Nothing to purge')
                    : HtmlTagGeneratorHelper::tag(
                        'a',
                        __('Purge'),
                        [
                            'href'  => vsprintf(
                                admin_url('admin-post.php') . '?action=cnq&_c_action=%s&argument=%s',
                                [
                                    ConfigurationProfilesController::ACTION_QUEUE_PURGE,
                                    Queue::QUEUE_NAME_DOWNLOAD_QUEUE,
                                ]
                            ),
                            'class' => 'ajaxcall',
                        ]
                    ),
            ],
        ];

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }


}