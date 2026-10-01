<?php

namespace Smartling\Extensions;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\DetectChangesHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * PublishPress Revisions stores a revision as a post (post_mime_type = *-revision, post_parent = original) and
 * deletes it after applying it to the original. Without this class, the submissions of the revision would be removed
 * by SubmissionCleanupHelper together with the link to the translated drafts.
 */
class PublishPressRevisions extends PluggableAbstract implements WPHookInterface
{
    use LoggerSafeTrait;

    public const REVISION_STATUSES = ['draft-revision', 'pending-revision', 'future-revision'];
    public const BASE_POST_META = '_rvy_base_post_id';

    /** @var array<int, int> original post id => blog id */
    private array $suppressedOriginals = [];

    /** @var array<int, array{originalId: int, blogId: int, contentType: string, upToDate: array<int, bool>}> revision id => move to do */
    private array $pendingMoves = [];

    public function __construct(
        PluginHelper $pluginHelper,
        WordpressFunctionProxyHelper $wpProxy,
        private LocalizationPluginProxyInterface $multilangProxy,
        private SubmissionManager $submissionManager,
        private DetectChangesHelper $detectChangesHelper,
        private ContentSerializationHelper $contentSerializationHelper,
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

        $this->wpProxy->add_filter('revisionary_apply_revision_data', [$this, 'recordPendingMove'], 10, 3);
        // priority is lower than that of SubmissionCleanupHelper, so submissions are moved before they are cleaned up
        $this->wpProxy->add_action('before_delete_post', [$this, 'moveSubmissions'], 5);
        $this->wpProxy->add_action('revision_applied', [$this, 'resumeChangeDetection']);
        $this->wpProxy->add_action('shutdown', [$this, 'resumeAllChangeDetection']);
        $this->wpProxy->add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, [$this, 'sanitizeTargetField'], 5, 3);
    }

    /**
     * Fires before PublishPress Revisions updates the original with the revision, and the update may fail. Nothing is
     * changed here except remembering what to move once the revision is deleted, which happens only after a
     * successful update.
     *
     * @param \WP_Post $revision
     * @param \WP_Post $published
     */
    public function recordPendingMove(mixed $update, mixed $revision, mixed $published)
    {
        if (!is_object($revision)
            || !is_object($published)
            || !property_exists($revision, 'ID')
            || !property_exists($published, 'ID')
            || !property_exists($published, 'post_type')
            || !property_exists($revision, 'post_type')
        ) {
            $this->getLogger()->warning('Invalid arguments passed to recordPendingMove');
            return $update;
        }
        $revisionId = ($revision->ID ?? 0);
        $originalId = ($published->ID ?? 0);
        if ($revisionId === 0 || $originalId === 0 || $revisionId === $originalId) {
            return $update;
        }

        try {
            $sourceBlogId = $this->wpProxy->get_current_blog_id();
            $contentType = $published->post_type ?? $revision->post_type ?? 'post';
            $upToDate = [];
            foreach ($this->submissionManager->find([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
                SubmissionEntity::FIELD_SOURCE_ID => $revisionId,
            ]) as $submission) {
                // the revision is not modified yet, so this tells if the translation matches its content
                $upToDate[(int)$submission->getId()] = $this->contentSerializationHelper->calculateHash($submission) === $submission->getSourceContentHash();
            }
            if ($upToDate !== []) {
                // the original is about to be updated with the content the submissions were created from
                $this->detectChangesHelper->suppress($sourceBlogId, $originalId);
                $this->suppressedOriginals[$originalId] = $sourceBlogId;
                $this->pendingMoves[$revisionId] = [
                    'originalId' => $originalId,
                    'blogId' => $sourceBlogId,
                    'contentType' => $contentType,
                    'upToDate' => $upToDate,
                ];
            }
        } catch (\Throwable $e) {
            $this->getLogger()->error("Unable to record submissions of revision id=$revisionId, original id=$originalId: {$e->getMessage()}");
        }

        return $update;
    }

    /**
     * Moves submissions of the revision that is being deleted after it was applied to the original
     */
    public function moveSubmissions(mixed $postId): void
    {
        $revisionId = (int)$postId;
        if (!array_key_exists($revisionId, $this->pendingMoves)) {
            return;
        }
        $pending = $this->pendingMoves[$revisionId];
        unset($this->pendingMoves[$revisionId]);

        try {
            foreach ($this->submissionManager->find([
                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $pending['blogId'],
                SubmissionEntity::FIELD_CONTENT_TYPE => $pending['contentType'],
                SubmissionEntity::FIELD_SOURCE_ID => $revisionId,
            ]) as $submission) {
                $this->moveSubmission($submission, $pending['originalId'], $pending['upToDate'][(int)$submission->getId()] ?? false);
            }
        } catch (\Throwable $e) {
            $this->getLogger()->error("Unable to preserve submissions of revision id=$revisionId, original id={$pending['originalId']}: {$e->getMessage()}");
        }
    }

    public function resumeChangeDetection(mixed $originalId): void
    {
        $originalId = (int)$originalId;
        if (array_key_exists($originalId, $this->suppressedOriginals)) {
            $this->detectChangesHelper->resume($this->suppressedOriginals[$originalId], $originalId);
            unset($this->suppressedOriginals[$originalId]);
        }
    }

    /**
     * revision_applied does not fire if PublishPress Revisions fails to update the original
     */
    public function resumeAllChangeDetection(): void
    {
        foreach (array_keys($this->suppressedOriginals) as $originalId) {
            $this->resumeChangeDetection($originalId);
        }
    }

    private function moveSubmission(SubmissionEntity $submission, int $originalId, bool $wasUpToDate): void
    {
        $existing = $this->submissionManager->findTargetBlogSubmission(
            $submission->getContentType(),
            $submission->getSourceBlogId(),
            $originalId,
            $submission->getTargetBlogId(),
        );

        if ($existing !== null) {
            if ($this->getAppliedAt($existing) > $this->getAppliedAt($submission)) {
                // original already has a newer translation, nothing to preserve
                return;
            }
            $this->unlink($existing);
            $this->submissionManager->delete($existing);
        }

        $this->unlink($submission);
        $submission->setSourceId($originalId);
        if ($wasUpToDate) {
            // hash was calculated from the revision, which has meta and fields the original doesn't
            $submission->setSourceContentHash($this->contentSerializationHelper->calculateHash($submission));
        }
        $submission = $this->submissionManager->storeEntity($submission);
        try {
            $this->multilangProxy->linkObjects($submission);
        } catch (\Throwable $e) {
            $this->getLogger()->notice("Unable to link objects for submission id={$submission->getId()}: {$e->getMessage()}");
        }
        $this->getLogger()->info("Moved submission id={$submission->getId()} to original post id=$originalId");
    }

    private function getAppliedAt(SubmissionEntity $submission): string
    {
        $appliedDate = $submission->getAppliedDate() ?? '';

        return str_starts_with($appliedDate, '0000') ? '' : $appliedDate;
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
     * The target of a revision must be an ordinary post: no revision status and no parent (the original is the parent).
     */
    public function sanitizeTargetField(mixed $name, mixed $value, mixed $submission = null)
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
