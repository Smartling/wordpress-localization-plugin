<?php

namespace Smartling\Services;

use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SubmissionCleanupHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

class BlogRemovalHandler implements WPHookInterface
{
    use LoggerSafeTrait;

    private SubmissionCleanupHelper $submissionCleanupHelper;
    private SubmissionManager $submissionManager;

    public function __construct(SubmissionCleanupHelper $submissionCleanupHelper, SubmissionManager $submissionManager) {
        $this->submissionCleanupHelper = $submissionCleanupHelper;
        $this->submissionManager = $submissionManager;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     */
    public function register(): void
    {
        add_action('delete_blog', [$this, 'blogRemovalHandler']);
    }

    /**
     * At this time the blog does not exist anymore
     * We need to remove all related submissions if any
     * And cleanup all
     *
     * @param int $blogId
     * @noinspection PhpMissingParamTypeInspection called by WordPress, not sure if typed
     */
    public function blogRemovalHandler($blogId): void
    {
        foreach ($this->submissionManager->find([SubmissionEntity::FIELD_TARGET_BLOG_ID => $blogId]) as $submission) {
            $this->getLogger()->debug("Deleting submissionId={$submission->getId()} that references deleted blog $blogId.");
            $this->submissionCleanupHelper->deleteSubmission($submission);
        }
    }
}
