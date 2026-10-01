<?php

namespace Smartling\Tests\Extensions;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Extensions\PublishPressRevisions;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\DetectChangesHelper;
use Smartling\Helpers\PluginHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class PublishPressRevisionsTest extends TestCase
{
    private function submission(int $sourceId, int $targetId, ?string $appliedDate = null, string $hash = 'old'): SubmissionEntity
    {
        $submission = new SubmissionEntity();
        $submission->setId($sourceId * 10);
        $submission->setContentType('page');
        $submission->setSourceBlogId(1);
        $submission->setTargetBlogId(2);
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);
        $submission->setSourceContentHash($hash);
        $submission->setAppliedDate($appliedDate);

        return $submission;
    }

    private function x(
        ?SubmissionManager $manager = null,
        ?LocalizationPluginProxyInterface $multilang = null,
        ?WordpressFunctionProxyHelper $wp = null,
        ?DetectChangesHelper $detectChanges = null,
        ?ContentSerializationHelper $serialization = null,
    ): PublishPressRevisions {
        if ($wp === null) {
            $wp = $this->createMock(WordpressFunctionProxyHelper::class);
            $wp->method('get_current_blog_id')->willReturn(1);
        }
        if ($serialization === null) {
            $serialization = $this->createMock(ContentSerializationHelper::class);
            $serialization->method('calculateHash')->willReturn('new');
        }

        return new PublishPressRevisions(
            $this->createMock(PluginHelper::class),
            $wp,
            $multilang ?? $this->createMock(LocalizationPluginProxyInterface::class),
            $manager ?? $this->createMock(SubmissionManager::class),
            $detectChanges ?? $this->createMock(DetectChangesHelper::class),
            $serialization,
        );
    }

    private function revision(): object
    {
        return (object)['ID' => 20, 'post_type' => 'page'];
    }

    private function original(): object
    {
        return (object)['ID' => 10, 'post_type' => 'page'];
    }

    public function testNothingIsChangedUntilRevisionIsDeleted(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->once())->method('find')->willReturn([$this->submission(20, 200)]);
        $manager->expects($this->never())->method('findTargetBlogSubmission');
        $manager->expects($this->never())->method('delete');
        $manager->expects($this->never())->method('storeEntity');
        $multilang = $this->createMock(LocalizationPluginProxyInterface::class);
        $multilang->expects($this->never())->method('unlinkObjects');
        $multilang->expects($this->never())->method('linkObjects');

        $update = ['a' => 'b'];
        $this->assertSame($update, $this->x($manager, $multilang)->recordPendingMove($update, $this->revision(), $this->original()));
    }

    public function testRevisionSubmissionIsMovedToOriginalWhenRevisionIsDeleted(): void
    {
        $submission = $this->submission(20, 200);
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->exactly(2))->method('find')->willReturn([$submission]);
        $manager->method('findTargetBlogSubmission')->willReturn(null);
        $manager->expects($this->never())->method('delete');
        $manager->expects($this->once())->method('storeEntity')
            ->with($this->callback(static fn(SubmissionEntity $s) => $s->getSourceId() === 10 && $s->getTargetId() === 200))
            ->willReturnArgument(0);
        $multilang = $this->createMock(LocalizationPluginProxyInterface::class);
        $multilang->expects($this->once())->method('unlinkObjects');
        $multilang->expects($this->once())->method('linkObjects');

        $x = $this->x($manager, $multilang);
        $x->recordPendingMove([], $this->revision(), $this->original());
        $x->moveSubmissions(20);
        $x->moveSubmissions(20);
    }

    public function testUnrelatedPostDeletionDoesNothing(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->never())->method('storeEntity');

        $this->x($manager)->moveSubmissions(99);
    }

    public function testHashIsRecalculatedOnlyForUpToDateSubmissions(): void
    {
        $upToDate = $this->submission(20, 200, null, 'current');
        $outdated = $this->submission(21, 201, null, 'stale');
        $serialization = $this->createMock(ContentSerializationHelper::class);
        $serialization->method('calculateHash')->willReturnCallback(
            static fn(SubmissionEntity $s) => $s->getSourceId() === 10 ? 'original-hash' : ($s->getId() === 200 ? 'current' : 'different'),
        );
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willReturn([$upToDate, $outdated]);
        $manager->method('findTargetBlogSubmission')->willReturn(null);
        $manager->method('storeEntity')->willReturnArgument(0);

        $x = $this->x($manager, null, null, null, $serialization);
        $x->recordPendingMove([], $this->revision(), $this->original());
        $x->moveSubmissions(20);

        $this->assertSame('original-hash', $upToDate->getSourceContentHash());
        $this->assertSame('stale', $outdated->getSourceContentHash());
    }

    public function testExistingOriginalSubmissionWithOlderTranslationIsReplaced(): void
    {
        $existing = $this->submission(10, 100, '2026-09-01 10:00:00');
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willReturn([$this->submission(20, 50, '2026-09-02 10:00:00')]);
        $manager->method('findTargetBlogSubmission')->willReturn($existing);
        $manager->expects($this->once())->method('delete')->with($existing);
        $manager->expects($this->once())->method('storeEntity')->willReturnArgument(0);

        $x = $this->x($manager);
        $x->recordPendingMove([], $this->revision(), $this->original());
        $x->moveSubmissions(20);
    }

    public function testExistingOriginalSubmissionWithNewerTranslationIsKept(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->exactly(2))->method('find')->willReturn([$this->submission(20, 900, '2026-09-01 10:00:00')]);
        $manager->expects($this->once())->method('findTargetBlogSubmission')->willReturn($this->submission(10, 100, '2026-09-02 10:00:00'));
        $manager->expects($this->never())->method('delete');
        $manager->expects($this->never())->method('storeEntity');

        $x = $this->x($manager);
        $x->recordPendingMove([], $this->revision(), $this->original());
        $x->moveSubmissions(20);
    }

    public function testFailureDoesNotBreakRevisionPublishing(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->once())->method('find')->willThrowException(new \RuntimeException('db down'));
        $update = ['a' => 'b'];

        $this->assertSame($update, $this->x($manager)->recordPendingMove($update, $this->revision(), $this->original()));
    }

    public function testInvalidArgumentsAreIgnored(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->never())->method('find');

        $this->assertSame([], $this->x($manager)->recordPendingMove([], (object)['ID' => 20], (object)['ID' => 10]));
    }

    public function testChangeDetectionIsSuppressedUntilRevisionIsApplied(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willReturn([$this->submission(20, 200)]);
        $detectChanges = $this->createMock(DetectChangesHelper::class);
        $detectChanges->expects($this->once())->method('suppress')->with(1, 10);
        $detectChanges->expects($this->once())->method('resume')->with(1, 10);
        $x = $this->x($manager, null, null, $detectChanges);

        $x->recordPendingMove([], $this->revision(), $this->original());
        $x->resumeChangeDetection(10);
        $x->resumeChangeDetection(10);
    }

    public function testChangeDetectionIsResumedOnShutdownWhenRevisionWasNotApplied(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willReturn([$this->submission(20, 200)]);
        $detectChanges = $this->createMock(DetectChangesHelper::class);
        $detectChanges->expects($this->once())->method('suppress')->with(1, 10);
        $detectChanges->expects($this->once())->method('resume')->with(1, 10);
        $x = $this->x($manager, null, null, $detectChanges);

        $x->recordPendingMove([], $this->revision(), $this->original());
        $x->resumeAllChangeDetection();
        $x->resumeAllChangeDetection();
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
}
