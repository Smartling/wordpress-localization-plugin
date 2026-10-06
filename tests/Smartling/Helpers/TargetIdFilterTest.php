<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\TargetIdFilter;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class TargetIdFilterTest extends TestCase
{
    private function makeFilter(?SubmissionEntity $found, ?array $expectedParameters = null, int $currentBlogId = 3): TargetIdFilter
    {
        $manager = $this->createMock(SubmissionManager::class);
        $expectation = $manager->expects($this->once())->method('findOne');
        if ($expectedParameters !== null) {
            $expectation->with($expectedParameters);
        }
        $expectation->willReturn($found);

        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('get_current_blog_id')->willReturn($currentBlogId);

        return new TargetIdFilter($manager, $proxy);
    }

    private function makeSubmission(int $targetId): SubmissionEntity
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getTargetId')->willReturn($targetId);

        return $submission;
    }

    public function testRegistersFilter(): void
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->expects($this->once())->method('add_filter')->with(ExportedAPI::FILTER_TARGET_ID, $this->anything(), 10, 5);

        (new TargetIdFilter($this->createMock(SubmissionManager::class), $proxy))->register();
    }

    public function testReturnsTargetIdForCurrentBlog(): void
    {
        $filter = $this->makeFilter($this->makeSubmission(1335), [
            SubmissionEntity::FIELD_SOURCE_ID => 13574,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => 3,
            SubmissionEntity::FIELD_CONTENT_TYPE => 'attachment',
        ]);

        $this->assertSame(1335, $filter->getTargetId(13574, 'attachment'));
    }

    public function testExplicitBlogsAreUsed(): void
    {
        $filter = $this->makeFilter($this->makeSubmission(7), [
            SubmissionEntity::FIELD_SOURCE_ID => 5,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => 2,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => 1,
        ]);

        $this->assertSame(7, $filter->getTargetId('5', '', 2, false, 1));
    }

    public function testNoTranslationReturnsNull(): void
    {
        $this->assertNull($this->makeFilter(null)->getTargetId(10, 'post'));
    }

    public function testNoTranslationFallsBackToSourceId(): void
    {
        $this->assertSame(10, $this->makeFilter(null)->getTargetId(10, 'post', null, true));
    }

    public function testZeroTargetIdIsNotATranslation(): void
    {
        $this->assertNull($this->makeFilter($this->makeSubmission(0))->getTargetId(10, 'post'));
        $this->assertSame(10, $this->makeFilter($this->makeSubmission(0))->getTargetId(10, 'post', null, true));
    }

    public function testInvalidSourceIdIsNotLookedUp(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->never())->method('findOne');
        $filter = new TargetIdFilter($manager, $this->createMock(WordpressFunctionProxyHelper::class));

        $this->assertNull($filter->getTargetId('abc', 'post', null, true));
        $this->assertNull($filter->getTargetId(0, 'post'));
        $this->assertNull($filter->getTargetId(null, 'post'));
    }
}
