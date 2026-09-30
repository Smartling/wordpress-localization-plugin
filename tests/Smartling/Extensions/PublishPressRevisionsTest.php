<?php

namespace Smartling\Tests\Extensions;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Extensions\PublishPressRevisions;
use Smartling\Helpers\DetectChangesHelper;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class PublishPressRevisionsTest extends TestCase
{
    private function submission(int $sourceId, int $targetId): SubmissionEntity
    {
        $submission = new SubmissionEntity();
        $submission->setContentType('page');
        $submission->setSourceBlogId(1);
        $submission->setTargetBlogId(2);
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);

        return $submission;
    }

    private function x(
        ?SubmissionManager $manager = null,
        ?LocalizationPluginProxyInterface $multilang = null,
        ?WordpressFunctionProxyHelper $wp = null,
        ?DetectChangesHelper $detectChanges = null,
    ): PublishPressRevisions {
        return new PublishPressRevisions(
            $this->createMock(PluginHelper::class),
            $wp ?? $this->createMock(WordpressFunctionProxyHelper::class),
            $multilang ?? $this->createMock(LocalizationPluginProxyInterface::class),
            $manager ?? $this->createMock(SubmissionManager::class),
            $detectChanges ?? $this->createMock(DetectChangesHelper::class),
        );
    }

    public function testRevisionSubmissionIsMovedToOriginal(): void
    {
        $submission = $this->submission(20, 200);
        $wp = $this->createMock(WordpressFunctionProxyHelper::class);
        $wp->method('get_current_blog_id')->willReturn(1);
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->once())->method('find')->willReturn([$submission]);
        $manager->method('findTargetBlogSubmission')->willReturn(null);
        $manager->expects($this->never())->method('delete');
        $manager->expects($this->once())->method('storeEntity')
            ->with($this->callback(static fn(SubmissionEntity $s) => $s->getSourceId() === 10 && $s->getTargetId() === 200))
            ->willReturnArgument(0);
        $multilang = $this->createMock(LocalizationPluginProxyInterface::class);
        $multilang->expects($this->once())->method('unlinkObjects');
        $multilang->expects($this->once())->method('linkObjects');

        $update = ['a' => 'b'];
        $this->assertSame($update, $this->x($manager, $multilang, $wp)->moveSubmissionsToOriginal(
            $update,
            (object)['ID' => 20, 'post_type' => 'page'],
            (object)['ID' => 10, 'post_type' => 'page'],
        ));
    }

    public function testExistingOriginalSubmissionWithOlderTargetIsReplaced(): void
    {
        $existing = $this->submission(10, 100);
        $wp = $this->createMock(WordpressFunctionProxyHelper::class);
        $wp->method('get_current_blog_id')->willReturn(1);
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willReturn([$this->submission(20, 200)]);
        $manager->method('findTargetBlogSubmission')->willReturn($existing);
        $manager->expects($this->once())->method('delete')->with($existing);
        $manager->expects($this->once())->method('storeEntity')->willReturnArgument(0);

        $this->x($manager, null, $wp)->moveSubmissionsToOriginal([], (object)['ID' => 20, 'post_type' => 'page'], (object)['ID' => 10, 'post_type' => 'page']);
    }

    public function testExistingOriginalSubmissionWithNewerTargetIsKept(): void
    {
        $wp = $this->createMock(WordpressFunctionProxyHelper::class);
        $wp->method('get_current_blog_id')->willReturn(1);
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willReturn([$this->submission(20, 200)]);
        $manager->method('findTargetBlogSubmission')->willReturn($this->submission(10, 300));
        $manager->expects($this->never())->method('delete');
        $manager->expects($this->never())->method('storeEntity');

        $this->x($manager, null, $wp)->moveSubmissionsToOriginal([], (object)['ID' => 20], (object)['ID' => 10, 'post_type' => 'page']);
    }

    public function testFailureDoesNotBreakRevisionPublishing(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willThrowException(new \RuntimeException('db down'));
        $update = ['a' => 'b'];

        $this->assertSame($update, $this->x($manager)->moveSubmissionsToOriginal($update, (object)['ID' => 20], (object)['ID' => 10]));
    }

    public function testRevisionMimeTypeIsResetOnTarget(): void
    {
        $x = $this->x();
        foreach (PublishPressRevisions::REVISION_STATUSES as $status) {
            $this->assertSame('', $x->sanitizeTargetField('post_mime_type', $status));
        }
        $this->assertSame('image/png', $x->sanitizeTargetField('post_mime_type', 'image/png'));
        $this->assertSame('title', $x->sanitizeTargetField('post_title', 'title'));
    }

    public function testParentIsResetOnlyForRevisions(): void
    {
        $wp = $this->createMock(WordpressFunctionProxyHelper::class);
        $wp->method('get_post')->willReturnMap([
            [20, (object)['post_mime_type' => 'draft-revision']],
            [30, (object)['post_mime_type' => '']],
        ]);
        $wp->method('getPostMeta')->willReturn('');
        $x = $this->x(null, null, $wp);

        $this->assertSame(0, $x->sanitizeTargetField('post_parent', 10, $this->submission(20, 0)));
        $this->assertSame(10, $x->sanitizeTargetField('post_parent', 10, $this->submission(30, 0)));
    }

    public function testChangeDetectionIsSuppressedUntilRevisionIsApplied(): void
    {
        $wp = $this->createMock(WordpressFunctionProxyHelper::class);
        $wp->method('get_current_blog_id')->willReturn(1);
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willReturn([]);
        $detectChanges = $this->createMock(DetectChangesHelper::class);
        $detectChanges->expects($this->once())->method('suppress')->with(1, 10);
        $detectChanges->expects($this->once())->method('resume')->with(1, 10);
        $x = $this->x($manager, null, $wp, $detectChanges);

        $x->moveSubmissionsToOriginal([], (object)['ID' => 20, 'post_type' => 'page'], (object)['ID' => 10, 'post_type' => 'page']);
        $x->resumeChangeDetection(10);
        $x->resumeChangeDetection(10);
    }
}
