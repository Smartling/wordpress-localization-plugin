<?php

namespace Smartling\WP\Controller;

use Smartling\FTS\FtsService;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * Instant Translation AJAX Controller
 *
 * Handles AJAX requests for instant translation feature
 */
class InstantTranslationController implements WPHookInterface
{
    use LoggerSafeTrait;

    private const ACTION_REQUEST_TRANSLATION = 'smartling_instant_translation';
    private const ACTION_POLL_STATUS = 'smartling_instant_translation_status';

    private FtsService $ftsService;
    private SubmissionManager $submissionManager;

    public function __construct(
        FtsService $ftsService,
        SubmissionManager $submissionManager
    ) {
        $this->ftsService = $ftsService;
        $this->submissionManager = $submissionManager;
    }

    public function register(): void
    {
        // Register AJAX handlers for both logged-in users
        add_action('wp_ajax_' . self::ACTION_REQUEST_TRANSLATION, [$this, 'handleRequestTranslation']);
        add_action('wp_ajax_' . self::ACTION_POLL_STATUS, [$this, 'handlePollStatus']);
    }

    /**
     * Handle instant translation request
     */
    public function handleRequestTranslation(): void
    {
        try {
            // Verify nonce if needed
            // check_ajax_referer('smartling_instant_translation_nonce');

            // Get parameters
            $contentType = sanitize_text_field($_POST['contentType'] ?? '');
            $contentId = intval($_POST['contentId'] ?? 0);
            $targetBlogId = intval($_POST['targetBlogId'] ?? 0);

            if (empty($contentType) || empty($contentId) || empty($targetBlogId)) {
                wp_send_json_error([
                    'message' => 'Missing required parameters: contentType, contentId, or targetBlogId'
                ], 400);
                return;
            }

            $this->getLogger()->info('Instant translation requested', [
                'contentType' => $contentType,
                'contentId' => $contentId,
                'targetBlogId' => $targetBlogId,
            ]);

            // Find or create submission
            $sourceBlogId = get_current_blog_id();
            $submissions = $this->submissionManager->find([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_SOURCE_ID => $contentId,
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ]);

            if (empty($submissions)) {
                // Create new submission
                $submission = $this->submissionManager->getSubmissionEntity(
                    $contentType,
                    $sourceBlogId,
                    $contentId,
                    $targetBlogId
                );
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                $submission = $this->submissionManager->storeEntity($submission);
            } else {
                $submission = reset($submissions);
            }

            // Validate submission
            if (!$submission || !$submission->getId()) {
                wp_send_json_error([
                    'message' => 'Failed to create or retrieve submission'
                ], 500);
                return;
            }

            // Set status to in progress
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
            $this->submissionManager->storeEntity($submission);

            // Start instant translation asynchronously
            // For now, we'll return success and let polling handle the progress
            // In a production environment, you might want to queue this operation

            wp_send_json_success([
                'submissionId' => $submission->getId(),
                'message' => 'Instant translation started'
            ]);

        } catch (\Exception $e) {
            $this->getLogger()->error('Instant translation request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            wp_send_json_error([
                'message' => 'Failed to start instant translation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle status polling request
     */
    public function handlePollStatus(): void
    {
        try {
            // Get submission ID
            $submissionId = intval($_POST['submissionId'] ?? 0);

            if (empty($submissionId)) {
                wp_send_json_error([
                    'message' => 'Missing required parameter: submissionId'
                ], 400);
                return;
            }

            // Get submission
            $submission = $this->submissionManager->getEntityById($submissionId);

            if (!$submission) {
                wp_send_json_error([
                    'message' => 'Submission not found'
                ], 404);
                return;
            }

            // Check if we need to actually start the translation
            // (This happens on first poll after request)
            if ($submission->getStatus() === SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS &&
                empty($submission->getFileUri())) {
                // Translation hasn't started yet, start it now
                $result = $this->ftsService->requestInstantTranslation($submission);

                if ($result['success']) {
                    // Refresh submission to get updated status
                    $submission = $this->submissionManager->getEntityById($submissionId);
                }
            }

            // Return current status
            wp_send_json_success([
                'status' => $this->mapSubmissionStatus($submission->getStatus()),
                'progress' => $submission->getCompletionPercentage(),
                'message' => $submission->getLastError() ?: ''
            ]);

        } catch (\Exception $e) {
            $this->getLogger()->error('Status poll failed', [
                'error' => $e->getMessage(),
                'submissionId' => $submissionId ?? 'unknown',
            ]);

            wp_send_json_error([
                'message' => 'Failed to get translation status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map submission status to frontend status
     */
    private function mapSubmissionStatus(string $submissionStatus): string
    {
        switch ($submissionStatus) {
            case SubmissionEntity::SUBMISSION_STATUS_COMPLETED:
                return 'completed';
            case SubmissionEntity::SUBMISSION_STATUS_FAILED:
            case SubmissionEntity::SUBMISSION_STATUS_CANCELLED:
                return 'failed';
            case SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS:
                return 'in_progress';
            case SubmissionEntity::SUBMISSION_STATUS_NEW:
            default:
                return 'pending';
        }
    }
}
