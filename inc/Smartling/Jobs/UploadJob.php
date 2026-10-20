<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\Cache;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\UploadQueueItem;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

class UploadJob extends JobAbstract
{
    public const JOB_HOOK_NAME = 'smartling-upload-task';

    /** @var int[] */
    private array $currentSubmissionIds = [];
    private float $startedAt = 0.0;

    public function __construct(
        ApiWrapperInterface $api,
        Cache $cache,
        private FileUriHelper $fileUriHelper,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        private UploadQueueManager $uploadQueueManager,
        private WordpressFunctionProxyHelper $wpProxy,
        int $throttleIntervalSeconds,
        string $jobRunInterval,
    ) {
        parent::__construct($api, $cache, $settingsManager, $submissionManager, $throttleIntervalSeconds, $jobRunInterval);
    }

    public function getJobHookName(): string
    {
        return self::JOB_HOOK_NAME;
    }

    protected function usesDistributedLock(): bool
    {
        return false;
    }

    public function run(string $source): void
    {
        $message = 'UploadJob';
        if ($source !== '') {
            $message .= ", source=\"$source\"";
        }
        $blogId = $this->wpProxy->get_current_blog_id();
        $message .= ", blogId=$blogId";
        $this->getLogger()->debug("Started $message");

        $this->startedAt = microtime(true);
        $this->currentSubmissionIds = [];
        // Runs in addition to Bootstrap::shutdownHandler(), which logs the fatal error itself
        register_shutdown_function([$this, 'logUnfinishedUpload']);

        $this->processUploadQueue($blogId);

        $this->currentSubmissionIds = [];
        $this->getLogger()->debug("Finished $message");
    }

    public function logUnfinishedUpload(): void
    {
        if ($this->currentSubmissionIds === []) {
            return;
        }
        $this->getLogger()->emergency(sprintf(
            'UploadJob terminated while processing submissionIds="%s", elapsed=%.2fs, peakMemory=%dB, memoryLimit="%s", maxExecutionTime="%s"',
            implode(',', $this->currentSubmissionIds),
            microtime(true) - $this->startedAt,
            memory_get_peak_usage(true),
            ini_get('memory_limit'),
            ini_get('max_execution_time'),
        ));
    }

    private function processUploadQueue(int $blogId): void
    {
        $processed = 0;
        $profiles = [];
        $max = $this->uploadQueueManager->length();
        while ($processed++ < $max) {
            $item = $this->uploadQueueManager->dequeue($blogId);
            if ($item === null) {
                break;
            }
            $submission = $item->getSubmissions()[0];
            $this->currentSubmissionIds = array_map(static fn($s) => $s->getId(), $item->getSubmissions());
            $this->getLogger()->debug("Retrieved upload queue item for submissionId={$submission->getId()}");
            if ($submission->isCloned()) {
                $this->getLogger()->debug("Skipping processing queue for submissionId={$submission->getId()}: was cloned");
                $this->uploadQueueManager->complete($item);
                continue;
            }
            if ($submission->getFileUri() === '') {
                $submission->setFileUri($this->fileUriHelper->generateFileUri($submission));
                $this->submissionManager->storeEntity($submission);
            }
            // Existing submissions are loaded from the queue by id, so they never pass through
            // SubmissionManager::getSubmissionEntity(): stamp the profile that was active when
            // translation was requested, for any submission in this batch that never got one
            // (e.g. created via a path that bypasses getSubmissionEntity()). Never overwrite an
            // existing stamp here - the batch was already created under that profile, and
            // re-stamping to whatever is active when this cron job happens to run would send
            // the upload with the wrong project's credentials against that batch.
            foreach ($item->getSubmissions() as $itemSubmission) {
                if ($itemSubmission->getConfigurationProfileId() === null) {
                    $this->submissionManager->stampConfigurationProfile($itemSubmission);
                    if ($itemSubmission->getConfigurationProfileId() !== null) {
                        $this->submissionManager->storeEntity($itemSubmission);
                    }
                }
            }
            $profileKey = $submission->getConfigurationProfileId() ?? "blog{$submission->getSourceBlogId()}";
            if (!array_key_exists($profileKey, $profiles)) {
                try {
                    $profiles[$profileKey] = $this->settingsManager->getProfileBySubmission($submission);
                } catch (SmartlingDbException) {
                    $this->failItem($item, 'Skipping upload of', "No active profile found for blogId={$submission->getSourceBlogId()}");
                    $this->uploadQueueManager->complete($item);
                    continue;
                }
            }
            $profile = $profiles[$profileKey];
            if ($item->getBatchUid() === '') {
                try {
                    $item = $item->setBatchUid($this->api->getOrCreateJobInfoForDailyBucketJob($profile, [$submission->getFileUri()])->getBatchUid());
                } catch (\Throwable $e) {
                    $this->failItem($item, 'Skipping upload of', $e->getMessage(), "failed to get or create daily bucket job: {$e->getMessage()}");
                    $this->uploadQueueManager->complete($item);
                    continue;
                }
            }

            $this->getLogger()->info(sprintf(
                'Cron Job upload for submissionId="%s" with status="%s" contentType="%s", sourceBlogId="%s", contentId="%s", targetBlogId="%s", targetLocale="%s", batchUid="%s"',
                $submission->getId(),
                $submission->getStatus(),
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
                $submission->getTargetBlogId(),
                $submission->getTargetLocale(),
                $item->getBatchUid(),
            ));

            try {
                $this->wpProxy->do_action(ExportedAPI::ACTION_SMARTLING_SEND_FOR_TRANSLATION, $item);
            } catch (\Throwable $e) {
                $this->failItem($item, 'Failing', $e->getMessage());
            }
            $this->uploadQueueManager->complete($item);
            $this->currentSubmissionIds = [];
            $this->placeLockFlag(true);
        }
    }

    private function failItem(UploadQueueItem $item, string $logVerb, string $errorMessage, ?string $logMessage = null): void
    {
        $logMessage ??= $errorMessage;
        foreach ($item->getSubmissions() as $submission) {
            $this->getLogger()->notice("$logVerb submissionId={$submission->getId()}: $logMessage");
            $this->submissionManager->setErrorMessage($submission, $errorMessage);
        }
    }
}
