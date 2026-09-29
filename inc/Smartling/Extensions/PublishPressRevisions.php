<?php

namespace Smartling\Extensions;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\DetectChangesHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * PublishPress Revisions stores a revision as a post (post_mime_type = *-revision, post_parent = original) and
 * deletes it after applying it to the original. Without this class the submissions of the revision would be removed
 * by SubmissionCleanupHelper together with the link to the translated drafts.
 */
class PublishPressRevisions extends PluggableAbstract implements WPHookInterface
{
    use LoggerSafeTrait;

    public const REVISION_STATUSES = ['draft-revision', 'pending-revision', 'future-revision'];
    public const BASE_POST_META = '_rvy_base_post_id';

    /** @var array<int, int> original post id => blog id */
    private array $suppressedOriginals = [];

    public function __construct(
        PluginHelper $pluginHelper,
        WordpressFunctionProxyHelper $wpProxy,
        private LocalizationPluginProxyInterface $multilangProxy,
        private SubmissionManager $submissionManager,
        private DetectChangesHelper $detectChangesHelper,
    ) {
        parent::__construct($pluginHelper, $wpProxy);
    }

    public function getMaxVersion(): string
    {
        return '4';
    }

    public function getMinVersion(): string
    {
        return '3';
    }

    public function getPluginId(): string
    {
        return 'publishpress-revisions';
    }

    public function getPluginPaths(): array
    {
        return ['revisionary/revisionary.php'];
    }

    public function register(): void
    {
        if ($this->getPluginSupportLevel() !== Pluggable::SUPPORTED) {
            return;
        }

        $this->wpProxy->add_filter('revisionary_apply_revision_data', [$this, 'moveSubmissionsToOriginal'], 10, 3);
        $this->wpProxy->add_action('revision_applied', [$this, 'resumeChangeDetection']);
        $this->wpProxy->add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, [$this, 'sanitizeTargetField'], 5, 3);
    }

    /**
     * Fires before PublishPress Revisions deletes the applied revision, so submissions can still be preserved.
     *
     * @param array|mixed $update
     * @param \WP_Post|object|mixed $revision
     * @param \WP_Post|object|mixed $published
     */
    public function moveSubmissionsToOriginal($update, $revision, $published)
    {
        $revisionId = (int)($revision->ID ?? 0);
        $originalId = (int)($published->ID ?? 0);
        if ($revisionId === 0 || $originalId === 0 || $revisionId === $originalId) {
            return $update;
        }

        try {
            $sourceBlogId = $this->wpProxy->get_current_blog_id();
            $contentType = (string)($published->post_type ?? $revision->post_type ?? 'post');
            // the original is about to be updated with the content the submissions were created from
            $this->detectChangesHelper->suppress($sourceBlogId, $originalId);
            $this->suppressedOriginals[$originalId] = $sourceBlogId;
            foreach ($this->submissionManager->find([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_SOURCE_ID => $revisionId,
            ]) as $submission) {
                $this->moveSubmission($submission, $originalId);
            }
        } catch (\Throwable $e) {
            $this->getLogger()->error("Unable to preserve submissions of revision id=$revisionId, original id=$originalId: {$e->getMessage()}");
        }

        return $update;
    }

    /**
     * @param int|mixed $originalId
     */
    public function resumeChangeDetection($originalId): void
    {
        $originalId = (int)$originalId;
        if (array_key_exists($originalId, $this->suppressedOriginals)) {
            $this->detectChangesHelper->resume($this->suppressedOriginals[$originalId], $originalId);
            unset($this->suppressedOriginals[$originalId]);
        }
    }

    private function moveSubmission(SubmissionEntity $submission, int $originalId): void
    {
        $existing = $this->submissionManager->findTargetBlogSubmission(
            $submission->getContentType(),
            $submission->getSourceBlogId(),
            $originalId,
            $submission->getTargetBlogId(),
        );

        if ($existing !== null) {
            if ($existing->getTargetId() > $submission->getTargetId()) {
                // original already has a newer translation, nothing to preserve
                return;
            }
            $this->unlink($existing);
            $this->submissionManager->delete($existing);
        }

        $this->unlink($submission);
        $submission->setSourceId($originalId);
        $submission = $this->submissionManager->storeEntity($submission);
        try {
            $this->multilangProxy->linkObjects($submission);
        } catch (\Throwable $e) {
            $this->getLogger()->notice("Unable to link objects for submission id={$submission->getId()}: {$e->getMessage()}");
        }
        $this->getLogger()->info("Moved submission id={$submission->getId()} to original post id=$originalId");
    }

    private function unlink(SubmissionEntity $submission): void
    {
        try {
            $this->multilangProxy->unlinkObjects($submission);
        } catch (\Throwable $e) {
            $this->getLogger()->notice("Unable to unlink objects for submission id={$submission->getId()}: {$e->getMessage()}");
        }
    }

    /**
     * Target of a revision must be an ordinary post: no revision status and no parent (the original is the parent).
     *
     * @param mixed $name
     * @param mixed $value
     * @param mixed $submission
     */
    public function sanitizeTargetField($name, $value, $submission = null)
    {
        if ($name === 'post_mime_type' && in_array($value, self::REVISION_STATUSES, true)) {
            return '';
        }

        if ($name === 'post_parent' && $submission instanceof SubmissionEntity && $this->isRevision($submission)) {
            return 0;
        }

        return $value;
    }

    private function isRevision(SubmissionEntity $submission): bool
    {
        $post = $this->wpProxy->get_post($submission->getSourceId());

        return is_object($post)
            && (in_array($post->post_mime_type ?? '', self::REVISION_STATUSES, true)
                || (int)$this->wpProxy->getPostMeta($submission->getSourceId(), self::BASE_POST_META, true) > 0);
    }
}
