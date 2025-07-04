<?php

namespace Smartling\WP\Table;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\JobAbstract;
use Smartling\Jobs\LastModifiedCheckJob;
use Smartling\Jobs\SubmissionCollectorJob;
use Smartling\Jobs\UploadJob;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;
use Smartling\WP\Controller\ConfigurationProfilesController;
use Smartling\WP\Controller\SmartlingListTable;
use Smartling\WP\WPHookInterface;

class QueueManagerTableWidget extends SmartlingListTable implements WPHookInterface
{
    private const MESSAGE_NOTHING_TO_DO = 'Nothing to do';
    private const MESSAGE_PROCESS = 'Process';
    private const MESSAGE_RUNNING = '<strong>Running, please wait...</strong>';

    public function register(): void
    {
    }

    public function __construct(
        private ApiWrapperInterface $api,
        private QueueInterface $queue,
        private SettingsManager $settingsManager,
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
        try {
            $profile = $this->settingsManager->getActiveProfile();
        } catch (EntityNotFoundException) {
            $profile = null;
        }
        $curStats = $this->queue->stats();
        $newSubmissionsCount = $this->uploadQueueManager->count();
        $collectorQueueSize = $this->submissionManager->getTotalInCheckStatusHelperQueue();
        $checkStatusPoolSize = $curStats[QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE] ?? 0;
        $downloadPoolSize = $curStats[QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE] ?? 0;

        $data = [
            [
                'cron_name'   => __('Upload'),
                'run_cron' => $this->getUploadCronActionCell($profile, $newSubmissionsCount),
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
                'run_cron' => $this->getCheckStatusHelperCronActionCell($profile, $collectorQueueSize),
                'queue_name'  => __('&nbsp;'),
                'queue_purge' => __('&nbsp;'),
            ],
            [
                'cron_name'   => __('Check Status'),
                'run_cron' => $this->getCheckStatusCronActionCell($profile, $checkStatusPoolSize),
                'queue_name' => QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE,
                'queue_purge' => $this->queuePurgeLink($checkStatusPoolSize === 0, QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_QUEUE),
            ],
            [
                'cron_name'   => __('Download'),
                'run_cron' => $this->getDownloadCronActionCell($profile, $downloadPoolSize),
                'queue_name' => QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE,
                'queue_purge' => $this->queuePurgeLink($downloadPoolSize === 0, QueueInterface::QUEUE_NAME_DOWNLOAD_QUEUE),
            ],
        ];

        if (($curStats[QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE] ?? 0) > 0) {
            $queueName = QueueInterface::QUEUE_NAME_LAST_MODIFIED_CHECK_AND_FAIL_QUEUE;
            $data[] = [
                'cron_name' => __('Manual Check Status'),
                'run_cron' => $this->getManualCheckStatusActionCell($profile, $curStats[$queueName]),
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

    private function getUploadCronActionCell(?ConfigurationProfileEntity $profile, int $count): string
    {
        if ($count === 0 && $this->submissionManager->findSubmissionForCloning() === null) {
            return self::MESSAGE_NOTHING_TO_DO;
        }

        $jobName = UploadJob::JOB_HOOK_NAME;
        try {
            $this->testLock($profile, $jobName);
            return sprintf(
                '%s (%s submissions waiting)',
                $this->getLockTag($jobName),
                $count,
            );
        } catch (SmartlingApiException $e) {
            return sprintf('%s (%s submissions queued)', $this->getRunningMessage($e), $count);
        }
    }

    private function getCheckStatusHelperCronActionCell(?ConfigurationProfileEntity $profile, int $count): string
    {
        if ($count === 0) {
            return __(self::MESSAGE_NOTHING_TO_DO);
        }

        $jobName = SubmissionCollectorJob::JOB_HOOK_NAME;
        try {
            $this->testLock($profile, $jobName);
            return sprintf(
                '%s (%s submissions)',
                $this->getLockTag($jobName),
                $count,
            );
        } catch (SmartlingApiException $e) {
            return $this->getRunningMessage($e);
        }
    }

    private function getCheckStatusCronActionCell(ConfigurationProfileEntity $profile, int $count): string
    {
        if ($count === 0) {
            return __(self::MESSAGE_NOTHING_TO_DO);
        }

        $jobName = LastModifiedCheckJob::JOB_HOOK_NAME;
        try {
            $this->testLock($profile, $jobName);

            return sprintf(
                '%s (%s submissions waiting)',
                $this->getLockTag($jobName),
                $count,
            );
        } catch (SmartlingApiException $e) {
            return sprintf('%s (%s submissions queued)', $this->getRunningMessage($e), $count);
        }
    }

    private function getDownloadCronActionCell(?ConfigurationProfileEntity $profile, int $count): string
    {
        if ($count === 0) {
            return __(self::MESSAGE_NOTHING_TO_DO);
        }

        $jobName = DownloadTranslationJob::JOB_HOOK_NAME;
        try {
            $this->testLock($profile, $jobName);
            return sprintf(
            '%s (%s submissions waiting)',
                $this->getLockTag($jobName),
                $count,
            );
        } catch (SmartlingApiException $e) {
            return sprintf('%s (%s submissions queued)', $this->getRunningMessage($e), $count);
        }
    }

    private function getManualCheckStatusActionCell(?ConfigurationProfileEntity $profile, int $count): string
    {
        $jobName = LastModifiedCheckJob::JOB_HOOK_NAME;
        try {
            $this->testLock($profile, $jobName);

            return sprintf(
                '%s (%s submissions waiting)',
                HtmlTagGeneratorHelper::tag(
                    'a',
                    __(self::MESSAGE_PROCESS),
                    [
                        'href' => sprintf(
                            '%s?action=cnq&_c_action=%s&argument=%s',
                            admin_url('admin-post.php'),
                            ConfigurationProfilesController::ACTION_QUEUE_FORCE,
                            $jobName,
                        ),
                        'class' => 'ajaxcall',
                    ]
                ),
                $count,
            );
        } catch (SmartlingApiException $e) {
            return sprintf('%s (%s submissions queued)', $this->getRunningMessage($e), $count);
        }
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

    private function getRunningMessage(SmartlingApiException $e): string
    {
        if (count($e->getErrorsByKey('resource.locked')) > 0) {
            return self::MESSAGE_RUNNING;
        }

        throw $e;
    }

    /**
     * @throws SmartlingApiException
     */
    private function testLock(?ConfigurationProfileEntity $profile, string $jobName): void
    {
        if ($profile !== null) {
            $this->api->acquireLock($profile, JobAbstract::CRON_FLAG_PREFIX . $jobName, 0.001);
        }
    }

    private function getLockTag(string $jobName): string
    {
        return HtmlTagGeneratorHelper::tag(
            'a',
            __(self::MESSAGE_PROCESS),
            [
                'href' => vsprintf(
                    '%s?action=cnq&_c_action=%s&argument=%s',
                    [
                        admin_url('admin-post.php'),
                        ConfigurationProfilesController::ACTION_QUEUE_FORCE,
                        $jobName,
                    ]
                ),
                'class' => 'ajaxcall',
            ]
        );
    }
}
