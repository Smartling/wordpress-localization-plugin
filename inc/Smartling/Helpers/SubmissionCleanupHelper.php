<?php
namespace Smartling\Helpers;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * Helper handles `before_delete_post` and `pre_delete_term` events (actions)
 * and checks if deleted post or term is a translation.
 * If so - adds corresponding submission to delete list.
 * Also checks if deleted post of term is an original content with submissions - deletes all submissions with
 * translations. Also if no submissions left for file - deletes file from smartling. Class SubmissionCleanupHelper
 * @package Smartling\Helpers
 */
class SubmissionCleanupHelper implements WPHookInterface
{
    use LoggerSafeTrait;

    private LocalizationPluginProxyInterface $multilangProxy;
    private SiteHelper $siteHelper;
    private SubmissionManager $submissionManager;

    public function __construct(LocalizationPluginProxyInterface $localizationPluginProxy, SiteHelper $siteHelper, SubmissionManager $submissionManager) {
        $this->multilangProxy = $localizationPluginProxy;
        $this->siteHelper = $siteHelper;
        $this->submissionManager = $submissionManager;
    }

    /**
     * Registers wp hook handlers. Invoked by WordPress.
     */
    public function register(): void
    {
        add_action('before_delete_post', [$this, 'beforeDeletePostHandler']);
        add_action('delete_attachment', [$this, 'deleteAttachmentHandler'], 10, 2);
        add_action('delete_widget', [$this, 'deleteWidgetHandler']);
        add_action('pre_delete_term', [$this, 'preDeleteTermHandler'], 999, 2);
    }

    /**
     * Used for testing
     */
    public function unregister(): void
    {
        remove_action('before_delete_post', [$this, 'beforeDeletePostHandler']);
        remove_action('delete_attachment', [$this, 'deleteAttachmentHandler']);
        remove_action('delete_widget', [$this, 'deleteWidgetHandler']);
        remove_action('pre_delete_term', [$this, 'preDeleteTermHandler'], 999);
    }

    /**
     * @param int $postId
     */
    public function beforeDeletePostHandler($postId): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        try {
            $currentBlogId = $this->siteHelper->getCurrentBlogId();
            $this->getLogger()->debug(vsprintf('Post id=%s is going to be deleted in blog=%s', [$postId, $currentBlogId]));
            global $post_type;

            if (is_null($post_type)) {
                $post_type = get_post($postId)->post_type;
            }

            $this->deleteSubmissions($post_type, $currentBlogId, (int)$postId);
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->warning($e->getMessage());
        }
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

    public function deleteWidgetHandler($widgetId): void
    {
        $currentBlogId = $this->siteHelper->getCurrentBlogId();
        $postType = get_post($widgetId)->post_type;
        $this->getLogger()->debug("Widget id=$widgetId type=$postType in blogId=$currentBlogId is going to be deleted");
        $this->deleteSubmissions($postType, $currentBlogId, $widgetId);
    }

    /**
     * @param int    $term
     * @param string $taxonomy
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
        $params = [
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $blogId,
            SubmissionEntity::FIELD_CONTENT_TYPE   => $contentType,
            SubmissionEntity::FIELD_TARGET_ID      => $contentId,
        ];
        $this->processDeletion($params);

        // try treat as original
        $params = [
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $blogId,
            SubmissionEntity::FIELD_CONTENT_TYPE   => $contentType,
            SubmissionEntity::FIELD_SOURCE_ID      => $contentId,
        ];

        $this->processDeletion($params);
    }

    /**
     * @param array $searchParams
     */
    private function processDeletion(array $searchParams): void
    {
        $this->getLogger()->debug(
            vsprintf('Looking for submissions matching next params: %s', [var_export($searchParams, true)])
        );

        $submissions = $this->submissionManager->find($searchParams);

        if (0 < count($submissions)) {
            $this->getLogger()->debug(vsprintf('Found %d submissions', [count($submissions)]));
            foreach ($submissions as $submission) {
                $this->unlinkContent($submission);
                $this->submissionManager->delete($submission);
            }
        } else {
            $this->getLogger()
                ->debug(vsprintf('No submissions found for search params: %s', [var_export($searchParams, true)]));
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
            $result = $this->multilangProxy->unlinkObjects($submission);
        } catch (\Exception $e) {
            $this->getLogger()->debug(
                vsprintf(
                    'An exception occurred while unlinking mlp relations. Message: %s',
                    [
                        $e->getMessage(),
                    ]
                ),
            );
        }

        $message = $result
            ? 'Successfully unlinked mlp relations for submission %s'
            : 'Due to unknown error mlp relations cannot be cleared for submission %s';

        $this->getLogger()->debug(vsprintf($message, [var_export($submission->toArray(false), true)]));
    }
}
