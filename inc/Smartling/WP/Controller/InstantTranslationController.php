<?php

namespace Smartling\WP\Controller;

use Smartling\FTS\FtsService;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionFactory;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

class InstantTranslationController implements WPHookInterface
{
    use LoggerSafeTrait;

    private const ACTION_REQUEST_TRANSLATION = 'smartling_instant_translation';
    private const ACTION_POLL_STATUS = 'smartling_instant_translation_status';

    public function __construct(
        private FtsService $ftsService,
        private SubmissionManager $submissionManager,
        private SubmissionFactory $submissionFactory,
        private FileUriHelper $fileUriHelper,
        private WordpressFunctionProxyHelper $wpProxy,
    ) {
    }

    public function register(): void
    {
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_REQUEST_TRANSLATION, [$this, 'handleRequestTranslation']);
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_POLL_STATUS, [$this, 'handlePollStatus']);
    }

    public function handleRequestTranslation(): void
    {
        $this->wpProxy->check_ajax_referer('smartling_instant_translation', '_wpnonce');

        if (!$this->wpProxy->current_user_can('publish_posts')) {
            $this->wpProxy->wp_send_json_error([
                'message' => 'Insufficient permissions'
            ], 403);
            return;
        }

        try {
            $contentType = $this->wpProxy->sanitize_text_field($this->wpProxy->wp_unslash($_POST['contentType'] ?? ''));
            $contentId = (int)($_POST['contentId'] ?? 0);
            $relations = $this->wpProxy->map_deep($this->wpProxy->wp_unslash($_POST['relations'] ?? []), 'sanitize_text_field');
            $targetBlogIds = array_map('intval', $_POST['targetBlogIds'] ?? []);

            if (empty($contentType) || empty($contentId) || empty($targetBlogIds)) {
                $this->wpProxy->wp_send_json_error([
                    'message' => 'Missing required parameters: contentType, contentId, or targetBlogIds'
                ], 400);
                return;
            }

            $relatedCount = $this->countRelatedItems($relations);
            $this->getLogger()->info(
                "Instant translation requested, contentId=$contentId, contentType=$contentType, " .
                "targetBlogIds=" . implode(',', $targetBlogIds) . ", relatedItemsCount=$relatedCount"
            );

            $sourceBlogId = $this->wpProxy->get_current_blog_id();

            $allSubmissions = $this->buildSubmissions(
                $contentType,
                $contentId,
                $sourceBlogId,
                $targetBlogIds,
                $relations,
            );

            if (empty($allSubmissions)) {
                $this->wpProxy->wp_send_json_error([
                    'message' => 'Failed to create submissions for translation'
                ], 500);
                return;
            }

            $submissionsBySource = [];
            foreach ($allSubmissions as $submission) {
                $key = $submission->getContentType() . ':' . $submission->getSourceId();
                $submissionsBySource[$key][] = $submission;
            }

            $this->getLogger()->info(
                "Processing " . count($allSubmissions) . " total submissions in " .
                count($submissionsBySource) . " source groups"
            );

            $allSubmissionIds = [];

            foreach ($submissionsBySource as $sourceKey => $sourceSubmissions) {
                $result = $this->ftsService->requestInstantTranslationBatch($sourceSubmissions);

                if ($result['success']) {
                    $submissionIds = array_map(static fn($s) => $s->getId(), $sourceSubmissions);
                    $allSubmissionIds = array_merge($allSubmissionIds, $submissionIds);
                    $this->getLogger()->info("Successfully started FTS batch for source: $sourceKey");
                } else {
                    foreach ($sourceSubmissions as $submission) {
                        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                        $submission->setLastError($result['message'] ?? 'Translation failed');
                        $this->submissionManager->storeEntity($submission);
                    }
                    $this->getLogger()->error("FTS batch failed for source: $sourceKey - " . ($result['message'] ?? 'Unknown error'));
                }
            }

            if (!empty($allSubmissionIds)) {
                $uniqueSourceCount = count($submissionsBySource);

                $this->wpProxy->wp_send_json_success([
                    'submissionIds' => $allSubmissionIds,
                    'message' => sprintf(
                        'Instant translation started for %d item(s) across %d locale(s)',
                        $uniqueSourceCount,
                        count($targetBlogIds),
                    )
                ]);
                return;
            } else {
                $this->wpProxy->wp_send_json_error([
                    'message' => 'Failed to start instant translation for all items'
                ], 500);
                return;
            }
        } catch (\Exception $e) {
            $this->getLogger()->error('Instant translation request failed: ' . $e->getMessage());

            $this->wpProxy->wp_send_json_error([
                'message' => 'Failed to start instant translation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function handlePollStatus(): void
    {
        $this->wpProxy->check_ajax_referer('smartling_instant_translation', '_wpnonce');

        if (!$this->wpProxy->current_user_can('publish_posts')) {
            $this->wpProxy->wp_send_json_error([
                'message' => 'Insufficient permissions'
            ], 403);
            return;
        }

        try {
            $submissionId = (int)($_POST['submissionId'] ?? 0);

            if ($submissionId <= 0) {
                $this->wpProxy->wp_send_json_error(['message' => 'Invalid submission ID'], 400);
                return;
            }

            $submission = $this->submissionManager->getEntityById($submissionId);

            if ($submission === null) {
                $this->wpProxy->wp_send_json_error(['message' => 'Submission not found'], 404);
                return;
            }

            $this->wpProxy->wp_send_json_success([
                'status' => $this->mapSubmissionStatus($submission->getStatus()),
                'progress' => $submission->getCompletionPercentage(),
                'message' => $submission->getLastError() ?: '',
            ]);
            return;
        } catch (\Exception $e) {
            $this->getLogger()->error("Status poll failed: " . $e->getMessage());
            $this->wpProxy->wp_send_json_error(['message' => 'Failed to get translation status: ' . $e->getMessage()], 500);
        }
    }

    private function mapSubmissionStatus(string $submissionStatus): string
    {
        return match ($submissionStatus) {
            SubmissionEntity::SUBMISSION_STATUS_COMPLETED => 'completed',
            SubmissionEntity::SUBMISSION_STATUS_FAILED, SubmissionEntity::SUBMISSION_STATUS_CANCELLED => 'failed',
            SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS => 'in_progress',
            default => 'pending',
        };
    }

    /**
     * @return SubmissionEntity[]
     */
    private function buildSubmissions(
        string $contentType,
        int $contentId,
        int $sourceBlogId,
        array $targetBlogIds,
        array $relations
    ): array {
        $submissions = [];

        foreach ($targetBlogIds as $targetBlogId) {
            $mainSubmission = $this->getOrCreateSubmission(
                $sourceBlogId,
                $targetBlogId,
                $contentType,
                $contentId,
            );
            if ($mainSubmission !== null) {
                $submissions[] = $mainSubmission;
            }

            $relatedSources = $this->getRelatedSources($relations, $targetBlogId, $contentType, $contentId);
            foreach ($relatedSources as $source) {
                $relatedSubmission = $this->getOrCreateSubmission(
                    $sourceBlogId,
                    $targetBlogId,
                    $source['type'],
                    $source['id']
                );
                if ($relatedSubmission !== null) {
                    $submissions[] = $relatedSubmission;
                }
            }
        }

        return $submissions;
    }

    /**
     * @return array Array of sources: [['id' => int, 'type' => string], ...]
     */
    private function getRelatedSources(
        array $relations,
        int $targetBlogId,
        string $mainContentType,
        int $mainContentId
    ): array {
        $sources = [];

        $relationSet = $relations[$targetBlogId] ?? [];
        foreach ($relationSet as $type => $ids) {
            foreach ($ids as $id) {
                if ($id === $mainContentId && $type === $mainContentType) {
                    $this->getLogger()->info(
                        "Related list contains reference to root content, skip adding sourceId=$id, contentType=$type"
                    );
                    continue;
                }

                $sources[] = [
                    'id' => $id,
                    'type' => $type,
                ];
            }
        }

        return $sources;
    }

    private function getOrCreateSubmission(
        int $sourceBlogId,
        int $targetBlogId,
        string $contentType,
        int $contentId
    ): ?SubmissionEntity {
        try {
            $submission = $this->submissionManager->findOne([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_SOURCE_ID => $contentId,
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            ]);

            if ($submission === null) {
                $submissionArray = [
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                    SubmissionEntity::FIELD_SOURCE_ID => $contentId,
                    SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                    SubmissionEntity::FIELD_STATUS => SubmissionEntity::SUBMISSION_STATUS_NEW,
                    SubmissionEntity::FIELD_SUBMISSION_DATE => DateTimeHelper::nowAsString(),
                ];
                $submission = $this->submissionFactory->fromArray($submissionArray);
                $submission->setFileUri($this->fileUriHelper->generateFileUri($submission));
            } else {
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
            }

            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
            return $this->submissionManager->storeEntity($submission);
        } catch (\Exception $e) {
            $this->getLogger()->error(
                "Failed to get/create submission for contentType=$contentType, contentId=$contentId: " .
                $e->getMessage()
            );
            return null;
        }
    }

    private function countRelatedItems(array $relations): int
    {
        $uniqueItems = [];

        foreach ($relations as $relationSet) {
            foreach ($relationSet as $type => $ids) {
                foreach ($ids as $id) {
                    $key = "$type:$id";
                    $uniqueItems[$key] = true;
                }
            }
        }

        return count($uniqueItems);
    }
}
