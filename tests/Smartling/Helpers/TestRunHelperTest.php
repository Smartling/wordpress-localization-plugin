<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingTestRunCheckFailedException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Models\GutenbergBlock;
use Smartling\Submissions\SubmissionEntity;

class TestRunHelperTest extends TestCase
{
    private ContentHelper $contentHelper;
    private GutenbergBlockHelper $gutenbergBlockHelper;
    private TestRunHelper $testRunHelper;

    protected function setUp(): void
    {
        $this->contentHelper = $this->createMock(ContentHelper::class);
        $this->gutenbergBlockHelper = $this->createMock(GutenbergBlockHelper::class);
        $this->testRunHelper = new TestRunHelper($this->contentHelper, $this->gutenbergBlockHelper);
    }

    public function testCheckDownloadedSubmissionThrowsExceptionWhenSourceNotFound(): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceBlogId')->willReturn(1);
        $submission->method('getSourceId')->willReturn(2);

        $this->contentHelper->expects($this->once())
            ->method('readSourceContent')
            ->willThrowException(new EntityNotFoundException());

        $this->expectException(SmartlingTestRunCheckFailedException::class);
        $this->expectExceptionMessage('Unable to get source content while checking test run download blogId=1, postId=2');

        $this->testRunHelper->checkDownloadedSubmission($submission);
    }

    public function testCheckDownloadedSubmissionThrowsExceptionWhenTargetNotFound(): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getTargetBlogId')->willReturn(3);
        $submission->method('getTargetId')->willReturn(4);

        $original = $this->createMock(PostEntityStd::class);
        $this->contentHelper->expects($this->once())
            ->method('readSourceContent')
            ->willReturn($original);
        $this->contentHelper->expects($this->once())
            ->method('readTargetContent')
            ->willThrowException(new EntityNotFoundException());

        $this->expectException(SmartlingTestRunCheckFailedException::class);
        $this->expectExceptionMessage('Unable to get target content while checking test run download blogId=3, postId=4');

        $this->testRunHelper->checkDownloadedSubmission($submission);
    }

    public function testCheckDownloadedSubmissionThrowsExceptionWhenBlockCountMismatch(): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $original = new PostEntityStd();
        $target = new PostEntityStd();

        $original->post_content = 'source content';
        $target->post_content = 'target content';

        $this->contentHelper->method('readSourceContent')->willReturn($original);
        $this->contentHelper->method('readTargetContent')->willReturn($target);

        $sourceBlocks = [$this->createMock(GutenbergBlock::class)];
        $targetBlocks = [];

        $this->gutenbergBlockHelper->method('getPostContentBlocks')
            ->willReturnMap([
                ['source content', $sourceBlocks],
                ['target content', $targetBlocks]
            ]);

        $this->expectException(SmartlingTestRunCheckFailedException::class);
        $this->expectExceptionMessage('Source and target block count does not match');

        $this->testRunHelper->checkDownloadedSubmission($submission);
    }

    public function testCheckDownloadedSubmissionThrowsExceptionWhenAttributeCountMismatch(): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $original = new PostEntityStd();
        $target = new PostEntityStd();
        $original->post_content = 'source content';
        $target->post_content = 'target content';

        $this->contentHelper->method('readSourceContent')->willReturn($original);
        $this->contentHelper->method('readTargetContent')->willReturn($target);

        $sourceBlock = $this->createMock(GutenbergBlock::class);
        $targetBlock = $this->createMock(GutenbergBlock::class);

        $sourceBlock->method('getAttributes')->willReturn(['attr1' => 'value1']);
        $targetBlock->method('getAttributes')->willReturn([]);
        $sourceBlock->method('getInnerBlocks')->willReturn([]);
        $targetBlock->method('getInnerBlocks')->willReturn([]);

        $this->gutenbergBlockHelper->method('getPostContentBlocks')
            ->willReturnMap([
                ['source content', [$sourceBlock]],
                ['target content', [$targetBlock]]
            ]);

        $this->expectException(SmartlingTestRunCheckFailedException::class);
        $this->expectExceptionMessage('Source and target block attributes count does not match');

        $this->testRunHelper->checkDownloadedSubmission($submission);
    }

    public function testCheckDownloadedSubmissionSucceedsWithMatchingStructure(): void
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $original = new PostEntityStd();
        $target = new PostEntityStd();
        $original->post_content = 'source content';
        $target->post_content = 'target content';

        $this->contentHelper->expects($this->once())->method('readSourceContent')->willReturn($original);
        $this->contentHelper->expects($this->once())->method('readTargetContent')->willReturn($target);

        $sourceBlock = $this->createMock(GutenbergBlock::class);
        $targetBlock = $this->createMock(GutenbergBlock::class);

        $sourceBlock->expects($this->once())->method('getAttributes')->willReturn(['attr1' => 'value1']);
        $targetBlock->expects($this->once())->method('getAttributes')->willReturn(['attr1' => 'translated_value1']);
        $sourceBlock->expects($this->once())->method('getInnerBlocks')->willReturn([]);
        $targetBlock->expects($this->once())->method('getInnerBlocks')->willReturn([]);

        $this->gutenbergBlockHelper->expects($this->exactly(2))->method('getPostContentBlocks')
            ->willReturnMap([
                ['source content', [$sourceBlock]],
                ['target content', [$targetBlock]]
            ]);

        $this->testRunHelper->checkDownloadedSubmission($submission);
    }
}
