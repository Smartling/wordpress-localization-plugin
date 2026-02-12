<?php

namespace Smartling\FTS;

use Smartling\ApiWrapperInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

/**
 * FTS (Fast Translation Service) Service
 *
 * Orchestrates the instant translation workflow:
 * 1. Generate and upload file for translation
 * 2. Submit for instant translation
 * 3. Poll for completion with exponential backoff
 * 4. Download and apply translated content
 */
class FtsService
{
    use LoggerSafeTrait;

    // Polling configuration
    private const INITIAL_POLL_INTERVAL_MS = 1000;      // 1 second
    private const MAX_BACKOFF_INTERVAL_MS = 30000;      // 30 seconds
    private const TIMEOUT_MS = 120000;                  // 2 minutes

    // Exponential backoff intervals in milliseconds
    private const POLL_INTERVALS = [
        1000,   // 1s
        2000,   // 2s
        4000,   // 4s
        8000,   // 8s
        16000,  // 16s
        // All subsequent polls use MAX_BACKOFF_INTERVAL_MS (30s)
    ];

    // FTS status states
    private const STATE_QUEUED = 'QUEUED';
    private const STATE_PROCESSING = 'PROCESSING';
    private const STATE_COMPLETED = 'COMPLETED';
    private const STATE_FAILED = 'FAILED';
    private const STATE_CANCELLED = 'CANCELLED';

    private FtsApiWrapper $ftsApiWrapper;
    private ApiWrapperInterface $apiWrapper;
    private SubmissionManager $submissionManager;
    private ContentHelper $contentHelper;
    private SmartlingCore $core;
    private SettingsManager $settingsManager;
    private XmlHelper $xmlHelper;
    private PostContentHelper $postContentHelper;

    public function __construct(
        FtsApiWrapper $ftsApiWrapper,
        ApiWrapperInterface $apiWrapper,
        SubmissionManager $submissionManager,
        ContentHelper $contentHelper,
        SmartlingCore $core,
        SettingsManager $settingsManager,
        XmlHelper $xmlHelper,
        PostContentHelper $postContentHelper
    ) {
        $this->ftsApiWrapper = $ftsApiWrapper;
        $this->apiWrapper = $apiWrapper;
        $this->submissionManager = $submissionManager;
        $this->contentHelper = $contentHelper;
        $this->core = $core;
        $this->settingsManager = $settingsManager;
        $this->xmlHelper = $xmlHelper;
        $this->postContentHelper = $postContentHelper;
    }

    /**
     * Request instant translation for a submission
     *
     * This is the main entry point for instant translation. It:
     * 1. Generates XML and uploads the file to Smartling FTS
     * 2. Submits it for instant translation
     * 3. Polls for completion with exponential backoff
     * 4. Downloads and applies the translation
     *
     * @param SubmissionEntity $submission Submission to translate
     * @return array Result with status and details
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function requestInstantTranslation(SubmissionEntity $submission): array
    {
        $startTime = microtime(true);

        $this->getLogger()->info(
            'Starting instant translation request',
            [
                'submissionId' => $submission->getId(),
                'contentType' => $submission->getContentType(),
                'sourceBlogId' => $submission->getSourceBlogId(),
                'targetBlogId' => $submission->getTargetBlogId(),
            ]
        );

        try {
            // Step 1: Generate XML and upload file to FTS
            $fileUid = $this->uploadFile($submission);

            // Step 2: Submit for instant translation
            $mtUid = $this->submitFile($submission, $fileUid);

            // Step 3: Poll until complete or timeout
            $pollResult = $this->pollUntilComplete($submission, $fileUid, $mtUid);

            if ($pollResult['status'] === 'completed') {
                // Step 4: Download and apply translation
                $this->downloadAndApply($submission, $fileUid, $mtUid);

                $elapsedTime = round((microtime(true) - $startTime) * 1000);

                $this->getLogger()->info(
                    'Instant translation completed successfully',
                    [
                        'submissionId' => $submission->getId(),
                        'fileUid' => $fileUid,
                        'mtUid' => $mtUid,
                        'elapsedTimeMs' => $elapsedTime,
                    ]
                );

                return [
                    'success' => true,
                    'status' => 'completed',
                    'fileUid' => $fileUid,
                    'mtUid' => $mtUid,
                    'elapsedTimeMs' => $elapsedTime,
                ];
            }

            // Timeout or failure
            return [
                'success' => false,
                'status' => $pollResult['status'],
                'message' => $pollResult['message'],
                'fileUid' => $fileUid,
                'mtUid' => $mtUid,
            ];

        } catch (\Exception $e) {
            $this->getLogger()->error(
                'Instant translation failed with exception',
                [
                    'submissionId' => $submission->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate XML and upload file to FTS
     *
     * @return string File UID from FTS
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function uploadFile(SubmissionEntity $submission): string
    {
        $this->getLogger()->debug('Preparing file for instant translation', [
            'submissionId' => $submission->getId(),
        ]);

        // Prepare content for upload using SmartlingCore
        $submission = $this->core->prepareUpload($submission);

        // Get XML content
        $xml = $this->core->getXMLFiltered($submission);

        // Create temporary file for upload
        $tempFile = tempnam(sys_get_temp_dir(), 'smartling_fts_');
        file_put_contents($tempFile, $xml);

        try {
            // Generate logical file name
            $fileName = sprintf(
                'instant-translation-%s-%d-%d.xml',
                $submission->getContentType(),
                $submission->getSourceId(),
                time()
            );

            // Upload to FTS
            $response = $this->ftsApiWrapper->uploadFile(
                $submission,
                $tempFile,
                $fileName,
                'xml'
            );

            $fileUid = $response['fileUid'] ?? null;

            if (empty($fileUid)) {
                throw new \RuntimeException('Failed to get file UID from upload response');
            }

            $this->getLogger()->info('File uploaded to FTS', [
                'submissionId' => $submission->getId(),
                'fileUid' => $fileUid,
                'fileName' => $fileName,
            ]);

            return $fileUid;

        } finally {
            // Clean up temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Submit file for instant translation
     *
     * @param string $fileUid File UID from upload
     * @return string Machine translation UID (mtUid)
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function submitFile(SubmissionEntity $submission, string $fileUid): string
    {
        $this->getLogger()->debug('Submitting file for instant translation', [
            'submissionId' => $submission->getId(),
            'fileUid' => $fileUid,
        ]);

        // Get source locale from WordPress for the source blog
        $sourceBlogId = $submission->getSourceBlogId();

        // Switch to source blog to get its locale
        switch_to_blog($sourceBlogId);
        $wpLocale = get_locale(); // e.g., "en_US"
        restore_current_blog();

        // Convert WordPress locale (en_US) to Smartling locale (en-US)
        $sourceLocaleId = str_replace('_', '-', $wpLocale);

        // Get target locale using the profile
        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
        $targetLocale = $profile->getSmartlingLocale($submission->getTargetBlogId());

        if (empty($targetLocale)) {
            throw new \RuntimeException('Failed to determine target locale for submission');
        }

        $targetLocaleIds = [$targetLocale];

        $response = $this->ftsApiWrapper->submitForInstantTranslation(
            $submission,
            $fileUid,
            $sourceLocaleId,
            $targetLocaleIds
        );

        $mtUid = $response['mtUid'] ?? null;

        if (empty($mtUid)) {
            throw new \RuntimeException('Failed to get MT UID from response');
        }

        $this->getLogger()->info('Translation request created', [
            'submissionId' => $submission->getId(),
            'fileUid' => $fileUid,
            'mtUid' => $mtUid,
            'sourceLocale' => $sourceLocaleId,
            'targetLocale' => $targetLocale,
        ]);

        return $mtUid;
    }

    /**
     * Poll for translation completion with exponential backoff
     *
     * Polling intervals: 1s -> 2s -> 4s -> 8s -> 16s -> 30s -> 30s -> ...
     * Timeout: 2 minutes
     *
     * @return array Status result
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function pollUntilComplete(SubmissionEntity $submission, string $fileUid, string $mtUid): array
    {
        $startTime = microtime(true);
        $intervalIndex = 0;

        $this->getLogger()->debug('Starting polling for instant translation', [
            'submissionId' => $submission->getId(),
            'fileUid' => $fileUid,
            'mtUid' => $mtUid,
            'timeoutMs' => self::TIMEOUT_MS,
        ]);

        while (true) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;

            // Check timeout
            if ($elapsedMs >= self::TIMEOUT_MS) {
                $this->getLogger()->warning('Instant translation polling timed out', [
                    'submissionId' => $submission->getId(),
                    'fileUid' => $fileUid,
                    'mtUid' => $mtUid,
                    'elapsedMs' => round($elapsedMs),
                ]);

                return [
                    'status' => 'timeout',
                    'message' => 'Translation request timed out after 2 minutes',
                ];
            }

            // Poll status
            $response = $this->ftsApiWrapper->pollTranslationStatus($submission, $fileUid, $mtUid);
            $state = $response['state'] ?? '';

            $this->getLogger()->debug('Polled translation status', [
                'submissionId' => $submission->getId(),
                'state' => $state,
                'elapsedMs' => round($elapsedMs),
            ]);

            // Check if completed
            if ($state === self::STATE_COMPLETED) {
                return [
                    'status' => 'completed',
                    'data' => $response,
                ];
            }

            // Check if failed
            if ($state === self::STATE_FAILED) {
                $error = $data['error'] ?? 'Translation request failed';
                return [
                    'status' => 'failed',
                    'message' => is_array($error) ? ($error['message'] ?? 'Unknown error') : $error,
                    'data' => $response,
                ];
            }

            // Check if cancelled
            if ($state === self::STATE_CANCELLED) {
                return [
                    'status' => 'cancelled',
                    'message' => 'Translation request was cancelled',
                    'data' => $response,
                ];
            }

            // Calculate next wait interval with exponential backoff
            $waitMs = $this->getNextPollInterval($intervalIndex);
            $intervalIndex++;

            $this->getLogger()->debug('Waiting before next poll', [
                'waitMs' => $waitMs,
                'nextIntervalIndex' => $intervalIndex,
            ]);

            // Sleep for the calculated interval (convert to microseconds)
            usleep($waitMs * 1000);
        }
    }

    /**
     * Get next polling interval using exponential backoff
     *
     * @param int $intervalIndex Current interval index
     * @return int Wait time in milliseconds
     */
    private function getNextPollInterval(int $intervalIndex): int
    {
        // Use predefined intervals, or max interval if we've exceeded the array
        if ($intervalIndex < count(self::POLL_INTERVALS)) {
            return self::POLL_INTERVALS[$intervalIndex];
        }

        // All subsequent polls use 30s
        return self::MAX_BACKOFF_INTERVAL_MS;
    }

    /**
     * Download and apply translated content
     *
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function downloadAndApply(SubmissionEntity $submission, string $fileUid, string $mtUid): void
    {
        $this->getLogger()->debug('Downloading and applying translation', [
            'submissionId' => $submission->getId(),
            'fileUid' => $fileUid,
            'mtUid' => $mtUid,
        ]);

        // Get target locale using the profile
        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
        $targetLocale = $profile->getSmartlingLocale($submission->getTargetBlogId());

        if (empty($targetLocale)) {
            throw new \RuntimeException('Failed to determine target locale for download');
        }

        // Download translated file
        $translatedXml = $this->ftsApiWrapper->downloadTranslatedFile(
            $submission,
            $fileUid,
            $mtUid,
            $targetLocale
        );

        // Apply translated content using SmartlingCoreUploadTrait
        $this->core->applyXML($submission, $translatedXml, $this->xmlHelper, $this->postContentHelper);

        // Update submission status
        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
        $submission->setCompletedStringCount($submission->getWordCount());
        $submission->setAppliedDate(date('c'));
        $this->submissionManager->storeEntity($submission);

        $this->getLogger()->info('Translation applied successfully', [
            'submissionId' => $submission->getId(),
            'fileUid' => $fileUid,
            'mtUid' => $mtUid,
        ]);
    }
}
