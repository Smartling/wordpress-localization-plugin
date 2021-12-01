<?php

namespace Smartling\Helpers;

use Smartling\Exception\SmartlingTestRunCheckFailedException;
use Smartling\Models\GutenbergBlock;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\TestRunController;

class TestRunHelper
{
    private SiteHelper $siteHelper;
    private GutenbergBlockHelper $gutenbergBlockHelper;

    public function __construct(SiteHelper $siteHelper, GutenbergBlockHelper $gutenbergBlockHelper)
    {
        $this->siteHelper = $siteHelper;
        $this->gutenbergBlockHelper = $gutenbergBlockHelper;
    }

    public static function isTestRunBlog($id): bool
    {
        return $id === SimpleStorageHelper::get(TestRunController::TEST_RUN_BLOG_ID_SETTING_NAME);
    }

    public function checkDownloadedSubmission(SubmissionEntity $submission): void
    {
        $original = $this->siteHelper->withBlog($submission->getSourceBlogId(), function () use ($submission) {
            return get_post($submission->getSourceId());
        });
        if (!$original instanceof \WP_Post) {
            throw new SmartlingTestRunCheckFailedException("Unable to get source post while checking test run download blogId={$submission->getSourceBlogId()}, postId={$submission->getSourceId()}");
        }
        $target = $this->siteHelper->withBlog($submission->getTargetBlogId(), function () use ($submission) {
            return get_post($submission->getTargetId());
        });
        if (!$target instanceof \WP_Post) {
            throw new SmartlingTestRunCheckFailedException("Unable to get target post while checking test run download blogId={$submission->getTargetBlogId()}, postId={$submission->getTargetId()}");
        }
        $sourceBlocks = $this->gutenbergBlockHelper->getPostContentBlocks($original->post_content);
        $targetBlocks = $this->gutenbergBlockHelper->getPostContentBlocks($target->post_content);
        if (count($sourceBlocks) !== count($targetBlocks)) {
            throw new SmartlingTestRunCheckFailedException("Source and target block count does not match");
        }
        $this->assertBlockStructureSame($sourceBlocks, $targetBlocks);
    }

    /**
     * @param GutenbergBlock[] $sourceBlocks
     * @param GutenbergBlock[] $targetBlocks
     */
    private function assertBlockStructureSame(array $sourceBlocks, array $targetBlocks): void
    {
        foreach ($sourceBlocks as $index => $sourceBlock) {
            $targetBlock = $targetBlocks[$index];
            if (count($sourceBlock->getAttributes()) !== count($targetBlock->getAttributes())) {
                throw new SmartlingTestRunCheckFailedException("Source and target block attributes count does not match");
            }
            $sourceInnerBlocks = $sourceBlock->getInnerBlocks();
            $targetInnerBlocks = $targetBlock->getInnerBlocks();
            if (count($sourceInnerBlocks) !== count($targetInnerBlocks)) {
                throw new SmartlingTestRunCheckFailedException("Source and target inner block count does not match");
            }
            $this->assertBlockStructureSame($sourceInnerBlocks, $targetInnerBlocks);
        }
    }
}
