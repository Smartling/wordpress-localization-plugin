<?php
namespace Smartling\Helpers;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * Helper handles `before_delete_post` and `pre_delete_term` events (actions)
 * If deleted post or term references source or target of a submission, mark it deleted
 * If no other submissions left for file, delete file from smartling
 */
class SubmissionCleanupHelper implements WPHookInterface
{
    use LoggerSafeTrait;

    private ApiWrapperInterface $apiWrapper;
    private SiteHelper $siteHelper;
    private SubmissionManager $submissionManager;
    private ContentEntitiesIOFactory $ioWrapper;
    private LocalizationPluginProxyInterface $localizationPluginProxy;

    public function __construct(ApiWrapperInterface $apiWrapper, SiteHelper $siteHelper, SubmissionManager $submissionManager, LocalizationPluginProxyInterface $localizationPluginProxy) {
        $this->apiWrapper = $apiWrapper;
        $this->siteHelper = $siteHelper;
        $this->submissionManager = $submissionManager;
        $this->localizationPluginProxy = $localizationPluginProxy;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     */
    public function register(): void
    {
        add_action('before_delete_post', [$this, 'beforeDeletePostHandler']);
        add_action('pre_delete_term', [$this, 'preDeleteTermHandler'], 999, 2);
        add_action('delete_attachment', [$this, 'deleteAttachmentHandler'], 10, 2);
    }

    /**
     * @param int $postId
     * @noinspection PhpMissingParamTypeInspection called by WordPress, not sure if typed
     */
    public function beforeDeletePostHandler($postId): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        remove_action('before_delete_post', [$this, 'beforeDeletePostHandler']);

        $currentBlogId = $this->siteHelper->getCurrentBlogId();
        $this->getLogger()->debug(vsprintf('Post id=%s is going to be deleted in blog=%s', [$postId, $currentBlogId]));
        global $post_type;

        if (is_null($post_type)) {
            $post_type = get_post($postId)->post_type;
        }

        $this->deleteSubmissions($post_type, $currentBlogId, (int)$postId);

        add_action('before_delete_post', [$this, 'beforeDeletePostHandler']);
    }

    /**
     * @param int $postId
     * @param \WP_Post $post
     * @noinspection PhpMissingParamTypeInspection called by WordPress, not sure if typed
     */
    public function deleteAttachmentHandler($postId, $post): void
    {
        $postType = $post->post_type ?? 'attachment';
        $currentBlogId = $this->siteHelper->getCurrentBlogId();
        $this->getLogger()->debug("Attachment id=$postId type=$postType in blogId=$currentBlogId is going to be deleted");
        $this->deleteSubmissions($postType, $currentBlogId, (int)$postId);
    }

    /**
     * @param int $term
     * @param string $taxonomy
     * @noinspection PhpMissingParamTypeInspection called by WordPress, not sure if typed
     */
    public function preDeleteTermHandler($term, $taxonomy): void
    {
        $currentBlogId = $this->siteHelper->getCurrentBlogId();

        $this->getLogger()->debug(
            vsprintf(
                'Term id=%s, taxonomy=%s is going to be deleted in blog=%s',
                [
                    $term,
                    $taxonomy,
                    $currentBlogId,
                ]
            )
        );

        try {
            $this->deleteSubmissions($taxonomy, $currentBlogId, (int)$term);
        } catch (\Exception $e) {
            $this->getLogger()->warning($e->getMessage());
        }
    }

    private function deleteSubmissions(string $contentType, int $blogId, int $contentId): void
    {
        // try treat as translation
        $this->processDeletion([
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $blogId,
            SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
            SubmissionEntity::FIELD_TARGET_ID => $contentId,
        ]);

        // try treat as original
        $this->processDeletion([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $blogId,
            SubmissionEntity::FIELD_CONTENT_TYPE => $contentType,
            SubmissionEntity::FIELD_SOURCE_ID => $contentId,
        ]);
    }

    private function processDeletion(array $searchParams): void
    {
        $this->getLogger()->debug(
            vsprintf('Looking for submissions matching next params: %s', [var_export($searchParams, true)])
        );

        $submissions = $this->submissionManager->find($searchParams);

        if (0 < count($submissions)) {
            $this->getLogger()->debug(vsprintf('Found %d submissions', [count($submissions)]));
            foreach ($submissions as $submission) {
                $this->deleteSubmission($submission);
            }
        } else {
            $this->getLogger()
                ->debug(vsprintf('No submissions found for search params: %s', [var_export($searchParams, true)]));
        }
    }

    public function deleteSubmission(SubmissionEntity $submission): void
    {
        $this->unlinkContent($submission);
        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_DELETED);
        $submission->setLastModified(new \DateTime());
        $this->submissionManager->storeEntity($submission);
        if ('' !== $submission->getStateFieldFileUri() && 0 === $this->submissionManager->find([SubmissionEntity::FIELD_FILE_URI => $submission->getFileUri()])) {
            $this->getLogger()->debug("File {$submission->getFileUri()} is not in use and will be deleted");
            $this->apiWrapper->deleteFile($submission);
        }
    }

    private function unlinkContent(SubmissionEntity $submission): void
    {
        $result = false;
        $this->getLogger()->debug(
            vsprintf(
                'Trying to unlink mlp relations for submission: %s',
                [
                    var_export($submission->toArray(false), true),
                ]
            )
        );

        try {
            $result = $this->localizationPluginProxy->unlinkObjects($submission);
        } catch (\Exception $e) {
            $this->getLogger()->debug(
                vsprintf(
                    'An exception occurred while unlinking mlp relations. Message: %s',
                    [
                        $e->getMessage(),
                    ]
                )
            );
        }

        $message = $result
            ? 'Successfully unlinked mlp relations for submission %s'
            : 'Due to unknown error mlp relations cannot be cleared for submission %s';

        $this->getLogger()->debug(vsprintf($message, [var_export($submission->toArray(false), true)]));
    }
}
