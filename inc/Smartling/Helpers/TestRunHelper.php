<?php

namespace Smartling\Helpers;

use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingTestRunCheckFailedException;
use Smartling\Models\GutenbergBlock;
use Smartling\Submissions\SubmissionEntity;

class TestRunHelper
{
    public const TEST_RUN_BLOG_ID_SETTING_NAME = 'smartling_TestRunBlogId';

    public function __construct(
        private ContentHelper $contentHelper,
        private GutenbergBlockHelper $gutenbergBlockHelper,
    ) {
    }

    public static function isTestRunBlog(int $id): bool
    {
        return $id === (int)SimpleStorageHelper::get(self::TEST_RUN_BLOG_ID_SETTING_NAME);
    }

    public function checkDownloadedSubmission(SubmissionEntity $submission): void
    {
        try {
            $original = $this->contentHelper->readSourceContent($submission);
        } catch (EntityNotFoundException) {
            throw new SmartlingTestRunCheckFailedException("Unable to get source content while checking test run download blogId={$submission->getSourceBlogId()}, postId={$submission->getSourceId()}");
        }
        try {
            $target = $this->contentHelper->readTargetContent($submission);
        } catch (EntityNotFoundException) {
            throw new SmartlingTestRunCheckFailedException("Unable to get target content while checking test run download blogId={$submission->getTargetBlogId()}, postId={$submission->getTargetId()}");
        }
        if ($original instanceof PostEntityStd && $target instanceof PostEntityStd) {
            $sourceBlocks = $this->gutenbergBlockHelper->getPostContentBlocks($original->post_content);
            $targetBlocks = $this->gutenbergBlockHelper->getPostContentBlocks($target->post_content);
            if (count($sourceBlocks) !== count($targetBlocks)) {
                throw new SmartlingTestRunCheckFailedException("Source and target block count does not match");
            }
            $this->assertBlockStructureSame($sourceBlocks, $targetBlocks);
        }
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
