<?php
namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Processors\ContentEntitiesIOFactory;
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
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ApiWrapperInterface
     */
    private $apiWrapper;

    /**
     * @var SiteHelper
     */
    private $siteHelper;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @var ContentEntitiesIOFactory
     */
    private $ioWrapper;

    /**
     * @var LocalizationPluginProxyInterface
     */
    private $multilangProxy;

    /**
     * SubmissionCleanupHelper constructor.
     */
    public function __construct() {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * @return SiteHelper
     */
    public function getSiteHelper()
    {
        return $this->siteHelper;
    }

    /**
     * @param SiteHelper $siteHelper
     */
    public function setSiteHelper($siteHelper)
    {
        $this->siteHelper = $siteHelper;
    }

    /**
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return ContentEntitiesIOFactory
     */
    public function getIoWrapper()
    {
        return $this->ioWrapper;
    }

    /**
     * @param ContentEntitiesIOFactory $ioWrapper
     */
    public function setIoWrapper($ioWrapper)
    {
        $this->ioWrapper = $ioWrapper;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return ApiWrapperInterface
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param ApiWrapperInterface $apiWrapper
     */
    public function setApiWrapper($apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;
    }

    /**
     * @return LocalizationPluginProxyInterface
     */
    public function getMultilangProxy()
    {
        return $this->multilangProxy;
    }

    /**
     * @param LocalizationPluginProxyInterface $multilangProxy
     */
    public function setMultilangProxy($multilangProxy)
    {
        $this->multilangProxy = $multilangProxy;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_action('before_delete_post', [$this, 'beforeDeletePostHandler']);
        add_action('pre_delete_term', [$this, 'preDeleteTermHandler'], 999, 2);
    }

    /**
     * @param int $postId
     */
    public function beforeDeletePostHandler($postId)
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        remove_action('before_delete_post', [$this, 'beforeDeletePostHandler']);
        try {
            $currentBlogId = $this->getSiteHelper()->getCurrentBlogId();
            $this->getLogger()->debug(vsprintf('Post id=%s is going to be deleted in blog=%s', [$postId, $currentBlogId]));
            global $post_type;

            if (is_null($post_type)) {
                $post_type = get_post($postId)->post_type;
            }

            $this->lookForSubmissions($post_type, $currentBlogId, (int)$postId);
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->warning($e->getMessage());
        }

        add_action('before_delete_post', [$this, 'beforeDeletePostHandler']);
    }

    /**
     * @param int    $term
     * @param string $taxonomy
     */
    public function preDeleteTermHandler($term, $taxonomy)
    {
        $currentBlogId = $this->getSiteHelper()->getCurrentBlogId();

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
            $this->lookForSubmissions($taxonomy, $currentBlogId, (int)$term);
        } catch (\Exception $e) {
            $this->getLogger()->warning($e->getMessage());
        }
    }

    /**
     * @param string $contentType
     * @param int    $blogId
     * @param int    $contentId
     */
    private function lookForSubmissions($contentType, $blogId, $contentId)
    {

        // try treat as translation
        $params = $searchParams = [
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
    private function processDeletion(array $searchParams)
    {
        $this->getLogger()->debug(
            vsprintf('Looking for submissions matching next params: %s', [var_export($searchParams, true)])
        );

        $submissions = $this->getSubmissionManager()->find($searchParams);

        if (0 < count($submissions)) {
            $this->getLogger()->debug(vsprintf('Found %d submissions', [count($submissions)]));
            foreach ($submissions as $submission) {
                $this->cancelSubmission($submission);
            }
        } else {
            $this->getLogger()
                ->debug(vsprintf('No submissions found for search params: %s', [var_export($searchParams, true)]));
        }
    }

    private function cancelSubmission(SubmissionEntity $submission)
    {
        $this->unlinkContent($submission);
        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_CANCELLED);
        $submission->setTargetId(0);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
    }

    /**
     * @param SubmissionEntity $submission
     */
    private function unlinkContent(SubmissionEntity $submission)
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
            $result = $this->getMultilangProxy()->unlinkObjects($submission);
        } catch (\Exception $e) {
            $this->getLogger()->debug(
                vsprintf(
                    'An exception occurred while unlinking mlp relations. Message: %s',
                    [
                        $e->getMessage(),
                    ]
                ), $e
            );
        }

        $message = $result
            ? 'Successfully unlinked mlp relations for submission %s'
            : 'Due to unknown error mlp relations cannot be cleared for submission %s';

        $this->getLogger()->debug(vsprintf($message, [var_export($submission->toArray(false), true)]));
    }
}