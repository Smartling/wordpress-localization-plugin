<?php

namespace Smartling\FTS;

use Smartling\ApiWrapperInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class FtsService
{
    use LoggerSafeTrait;

    private const INITIAL_POLL_INTERVAL_MS = 1000;
    private const MAX_BACKOFF_INTERVAL_MS = 30000;
    private const TIMEOUT_MS = 120000;

    private const STATE_QUEUED = 'QUEUED';
    private const STATE_PROCESSING = 'PROCESSING';
    private const STATE_COMPLETED = 'COMPLETED';
    private const STATE_FAILED = 'FAILED';
    private const STATE_CANCELLED = 'CANCELLED';

    public function __construct(
        private ApiWrapperInterface $apiWrapper,
        private FtsApiWrapper $ftsApiWrapper,
        private PostContentHelper $postContentHelper,
        private SettingsManager $settingsManager,
        private SiteHelper $siteHelper,
        private SubmissionManager $submissionManager,
        private SmartlingCore $core,
        private XmlHelper $xmlHelper,
    ) {
    }

    public function requestInstantTranslation(SubmissionEntity $submission): array
    {
        $this->getLogger()->info("Starting instant translation request, submissionId={$submission->getId()}, contentType={$submission->getContentType()}, sourceBlogId={$submission->getSourceBlogId()}, targetBlogId={$submission->getTargetBlogId()}");

        try {
            $fileUid = $this->uploadFile($submission);
            $mtUid = $this->submitFile($submission, $fileUid);
            $pollResult = $this->pollUntilComplete($submission, $fileUid, $mtUid);

            if ($pollResult['status'] === self::STATE_COMPLETED) {
                $this->downloadAndApply($submission, $fileUid, $mtUid);

                $this->getLogger()->info("Instant translation completed successfully, submissionId={$submission->getId()}, contentType={$submission->getContentType()}, sourceBlogId={$submission->getSourceBlogId()}");

                return [
                    'success' => true,
                    'status' => self::STATE_COMPLETED,
                    'fileUid' => $fileUid,
                    'mtUid' => $mtUid,
                ];
            }

            return [
                'success' => false,
                'status' => $pollResult['status'],
                'message' => $pollResult['message'],
                'fileUid' => $fileUid,
                'mtUid' => $mtUid,
            ];

        } catch (\Exception $e) {
            $this->getLogger()->error("Instant translation failed with exception, submissionId={$submission->getId()}, message={$e->getMessage()}");

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param SubmissionEntity[] $submissions
     * @throws \JsonException
     */
    public function requestInstantTranslationBatch(array $submissions): array
    {
        if (empty($submissions)) {
            return [
                'success' => false,
                'message' => 'No submissions provided',
            ];
        }

        $firstSubmission = $submissions[0];
        $submissionIds = array_map(static fn($s) => $s->getId(), $submissions);
        foreach ($submissions as $submission) {
            if ($submission->getSourceId() !== $firstSubmission->getSourceId()) {
                return [
                    'success' => false,
                    'message' => 'Same source submissions expected',
                ];
            }
        }

        $this->getLogger()->info(
            "Starting batch instant translation request, submissionIds=" . implode(',', $submissionIds) .
            ", contentType={$firstSubmission->getContentType()}, sourceBlogId={$firstSubmission->getSourceBlogId()}"
        );

        try {
            $fileUid = $this->uploadFile($firstSubmission);

            $profile = $this->settingsManager->getSingleSettingsProfile($firstSubmission->getSourceBlogId());
            $sourceLocale = $this->apiWrapper->getSourceLocale($profile);
            $targetLocales = [];

            foreach ($submissions as $submission) {
                $targetLocale = $profile->getSmartlingLocale($submission->getTargetBlogId());
                if (empty($targetLocale)) {
                    throw new \RuntimeException("Failed to determine target locale for submission {$submission->getId()}");
                }
                $targetLocales[] = $targetLocale;
            }

            $mtUid = $this->ftsApiWrapper->submitForInstantTranslation(
                $firstSubmission,
                $fileUid,
                $sourceLocale,
                $targetLocales,
            );

            $this->getLogger()->info(
                "Batch translation request created, fileUid=$fileUid, mtUid=$mtUid, sourceLocale=$sourceLocale, targetLocales=" .
                implode(',', $targetLocales)
            );

            $pollResult = $this->pollUntilComplete($firstSubmission, $fileUid, $mtUid);

            if ($pollResult['status'] === self::STATE_COMPLETED) {
                $succeededSubmissions = [];
                $failedSubmissions = [];

                foreach ($submissions as $submission) {
                    try {
                        $this->downloadAndApply($submission, $fileUid, $mtUid);
                        $succeededSubmissions[] = $submission->getId();
                        $this->getLogger()->info("Translation applied for submission {$submission->getId()}");
                    } catch (\Exception $e) {
                        $failedSubmissions[] = [
                            'id' => $submission->getId(),
                            'error' => $e->getMessage()
                        ];
                        $this->getLogger()->error(
                            "Failed to apply translation for submission {$submission->getId()}: {$e->getMessage()}"
                        );
                        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                        $submission->setLastError($e->getMessage());
                        $this->submissionManager->storeEntity($submission);
                    }
                }

                $allSucceeded = empty($failedSubmissions);

                $this->getLogger()->info(
                    "Batch instant translation completed, succeeded=" . count($succeededSubmissions) .
                    ", failed=" . count($failedSubmissions) . ", submissionIds=" . implode(',', $submissionIds)
                );

                return [
                    'success' => $allSucceeded,
                    'status' => $allSucceeded ? self::STATE_COMPLETED : 'partial_success',
                    'succeeded' => $succeededSubmissions,
                    'failed' => $failedSubmissions,
                    'fileUid' => $fileUid,
                    'mtUid' => $mtUid,
                ];
            }

            foreach ($submissions as $submission) {
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                $submission->setLastError($pollResult['message'] ?? 'Translation failed');
                $this->submissionManager->storeEntity($submission);
            }

            return [
                'success' => false,
                'status' => $pollResult['status'],
                'message' => $pollResult['message'],
                'fileUid' => $fileUid,
                'mtUid' => $mtUid,
            ];

        } catch (\Exception $e) {
            $this->getLogger()->error(
                "Batch instant translation failed with exception, submissionIds=" . implode(',', $submissionIds) .
                ", message={$e->getMessage()}"
            );

            foreach ($submissions as $submission) {
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                $submission->setLastError($e->getMessage());
                $this->submissionManager->storeEntity($submission);
            }

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return string FileUid from FTS
     */
    private function uploadFile(SubmissionEntity $submission): string
    {
        $this->getLogger()->debug("Preparing file for instant translation, submissionId={$submission->getId()}");

        $submission = $this->core->prepareUpload($submission);

        $tempFile = tempnam(sys_get_temp_dir(), 'smartling_fts_');
        file_put_contents($tempFile, $this->core->getXMLFiltered($submission));

        try {
            $fileName = sprintf(
                'instant-translation-%s-%d-%d.xml',
                $submission->getContentType(),
                $submission->getSourceId(),
                time()
            );

            $fileUid = $this->ftsApiWrapper->uploadFile($submission, $tempFile, $fileName);
            $this->getLogger()->info("File uploaded to FTS, submissionId={$submission->getId()}, fileUid=$fileUid");

            return $fileUid;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function submitFile(SubmissionEntity $submission, string $fileUid): string
    {
        $this->getLogger()->debug("Submitting file for instant translation, submissionId={$submission->getId()}, fileUid=$fileUid");

        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
        $sourceLocale = $this->apiWrapper->getSourceLocale($profile);
        $targetLocale = $profile->getSmartlingLocale($submission->getTargetBlogId());

        if (empty($targetLocale)) {
            throw new \RuntimeException('Failed to determine target locale for submission');
        }

        $mtUid = $this->ftsApiWrapper->submitForInstantTranslation(
            $submission,
            $fileUid,
            $sourceLocale,
            [$targetLocale]
        );

        $this->getLogger()->info("Translation request created, submissionId={$submission->getId()}, fileUid=$fileUid, mtUid=$mtUid, sourceLocale=$sourceLocale, targetLocale=$targetLocale");

        return $mtUid;
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function pollUntilComplete(SubmissionEntity $submission, string $fileUid, string $mtUid): array
    {
        $startTime = microtime(true);
        $waitMs = null;

        $this->getLogger()->debug("Starting polling for instant translation, submissionId={$submission->getId()}, fileUid=$fileUid, mtUid=$mtUid");

        while (true) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;

            if ($elapsedMs >= self::TIMEOUT_MS) {
                $this->getLogger()->warning("Instant translation polling timed out, submissionId={$submission->getId()}, fileUid=$fileUid, mtUid=$mtUid");

                return [
                    'status' => 'timeout',
                    'message' => 'Translation request timed out after ' . round(self::TIMEOUT_MS / 1000 / 60) . ' minutes',
                ];
            }

            $response = $this->ftsApiWrapper->pollTranslationStatus($submission, $fileUid, $mtUid);
            $state = $response['state'] ?? '';

            $this->getLogger()->debug("Polled translation status, submissionId={$submission->getId()}, fileUid=$fileUid, mtUid=$mtUid, state=$state");

            switch ($state) {
                case self::STATE_COMPLETED:
                    return [
                        'status' => self::STATE_COMPLETED,
                        'data' => $response,
                    ];
                case self::STATE_FAILED:
                    $error = $response['error'] ?? 'Translation request failed';
                    return [
                        'status' => self::STATE_FAILED,
                        'message' => is_array($error) ? ($error['message'] ?? 'Unknown error') : $error,
                        'data' => $response,
                    ];
                case self::STATE_CANCELLED:
                    return [
                        'status' => self::STATE_CANCELLED,
                        'message' => 'Translation request was cancelled',
                        'data' => $response,
                    ];
            }

            $waitMs = $this->getNextPollInterval($waitMs);

            $this->getLogger()->debug("Waiting before next poll, waitMs=$waitMs");

            usleep($waitMs * 1000);
        }
    }

    public function getNextPollInterval(?int $waitMs): int
    {
        if ($waitMs === null || $waitMs < self::INITIAL_POLL_INTERVAL_MS) {
            return self::INITIAL_POLL_INTERVAL_MS;
        }
        return min($waitMs * 2, self::MAX_BACKOFF_INTERVAL_MS);
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     * @throws \JsonException
     */
    private function downloadAndApply(SubmissionEntity $submission, string $fileUid, string $mtUid): void
    {
        $this->getLogger()->info("Downloading and applying translation, submissionId={$submission->getId()}, fileUid=$fileUid, mtUid=$mtUid");

        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
        $targetLocale = $profile->getSmartlingLocale($submission->getTargetBlogId());

        if (empty($targetLocale)) {
            throw new \RuntimeException('Failed to determine target locale for download');
        }

        $translatedXml = $this->ftsApiWrapper->downloadTranslatedFile(
            $submission,
            $fileUid,
            $mtUid,
            $targetLocale,
        );

        $this->core->applyXML($submission, $translatedXml, $this->xmlHelper, $this->postContentHelper);

        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
        $submission->setCompletedStringCount($submission->getWordCount());
        $submission->setAppliedDate(DateTimeHelper::nowAsString());
        $this->submissionManager->storeEntity($submission);

        $this->getLogger()->info("Translation applied successfully, submissionId={$submission->getId()}");
    }
}
