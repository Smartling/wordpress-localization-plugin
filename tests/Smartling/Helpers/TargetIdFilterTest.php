<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Base\ExportedAPI;
use Smartling\Helpers\Cache;
use Smartling\Helpers\TargetIdFilter;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class TargetIdFilterTest extends TestCase
{
    private function makeCache(): Cache
    {
        return new class implements Cache {
            public array $data = [];

            public function delete(string $key): bool
            {
                unset($this->data[$key]);

                return true;
            }

            public function get(string $key): mixed
            {
                return $this->data[$key] ?? false;
            }

            public function set(string $key, mixed $data, ?int $expire = null): bool
            {
                $this->data[$key] = $data;

                return true;
            }
        };
    }

    /**
     * @param SubmissionEntity[] $found
     */
    private function makeFilter(array $found, ?array $expectedParameters = null, int $currentBlogId = 3, ?Cache $cache = null, int $calls = 1): TargetIdFilter
    {
        $manager = $this->createMock(SubmissionManager::class);
        $expectation = $manager->expects($this->exactly($calls))->method('find');
        if ($expectedParameters !== null) {
            $expectation->with($expectedParameters, 2);
        }
        $expectation->willReturn($found);

        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('get_current_blog_id')->willReturn($currentBlogId);

        return new TargetIdFilter($manager, $proxy, $cache ?? $this->makeCache());
    }

    private function makeSubmission(int $targetId): SubmissionEntity
    {
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getTargetId')->willReturn($targetId);

        return $submission;
    }

    private function makeUnusedFilter(): TargetIdFilter
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->expects($this->never())->method('find');

        return new TargetIdFilter($manager, $this->createMock(WordpressFunctionProxyHelper::class), $this->makeCache());
    }

    public function testRegistersFilter(): void
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->expects($this->once())->method('add_filter')->with(ExportedAPI::FILTER_TARGET_ID, $this->anything(), 10, 5);

        (new TargetIdFilter($this->createMock(SubmissionManager::class), $proxy, $this->makeCache()))->register();
    }

    public function testReturnsTargetIdForCurrentBlog(): void
    {
        $filter = $this->makeFilter([$this->makeSubmission(1335)], [
            SubmissionEntity::FIELD_SOURCE_ID => 13574,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => 3,
            SubmissionEntity::FIELD_CONTENT_TYPE => 'attachment',
        ]);

        $this->assertSame(1335, $filter->getTargetId(13574, 'attachment'));
    }

    public function testExplicitBlogsAreUsed(): void
    {
        $filter = $this->makeFilter([$this->makeSubmission(7)], [
            SubmissionEntity::FIELD_SOURCE_ID => 5,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => 2,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => 1,
        ]);

        $this->assertSame(7, $filter->getTargetId('5', '', 2, false, 1));
    }

    public function testSourceBlogIdIsCombinedWithContentType(): void
    {
        $filter = $this->makeFilter([$this->makeSubmission(7)], [
            SubmissionEntity::FIELD_SOURCE_ID => 5,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => 3,
            SubmissionEntity::FIELD_CONTENT_TYPE => 'post',
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => 1,
        ]);

        $this->assertSame(7, $filter->getTargetId(5, 'post', null, true, 1));
    }

    public function testNoTranslationReturnsNull(): void
    {
        $this->assertNull($this->makeFilter([])->getTargetId(10, 'post', null, false));
    }

    public function testNoTranslationFallsBackToSourceIdByDefault(): void
    {
        $this->assertSame(10, $this->makeFilter([])->getTargetId(10, 'post'));
    }

    public function testAmbiguousResultIsNotATranslation(): void
    {
        $submissions = [$this->makeSubmission(1), $this->makeSubmission(2)];

        $this->assertNull($this->makeFilter($submissions)->getTargetId(10, '', null, false));
        $this->assertSame(10, $this->makeFilter($submissions)->getTargetId(10));
    }

    public function testZeroTargetIdIsNotATranslation(): void
    {
        $this->assertNull($this->makeFilter([$this->makeSubmission(0)])->getTargetId(10, 'post', null, false));
        $this->assertSame(10, $this->makeFilter([$this->makeSubmission(0)])->getTargetId(10, 'post'));
    }

    public function testInvalidSourceIdIsReturnedUnchangedWithFallback(): void
    {
        $filter = $this->makeUnusedFilter();

        $this->assertSame('abc', $filter->getTargetId('abc', 'post'));
        $this->assertSame(0, $filter->getTargetId(0, 'post'));
        $this->assertSame('', $filter->getTargetId('', 'post'));
        $this->assertNull($filter->getTargetId(null, 'post'));
        $this->assertSame(-5, $filter->getTargetId(-5));
    }

    public function testInvalidSourceIdReturnsNullWithoutFallback(): void
    {
        $filter = $this->makeUnusedFilter();

        $this->assertNull($filter->getTargetId('abc', 'post', null, false));
        $this->assertNull($filter->getTargetId(0, 'post', null, false));
        $this->assertNull($filter->getTargetId(1.5, 'post', null, false));
        $this->assertNull($filter->getTargetId('1e3', 'post', null, false));
    }

    public function testUnexpectedArgumentTypesDoNotThrow(): void
    {
        $filter = $this->makeFilter([$this->makeSubmission(9)], [
            SubmissionEntity::FIELD_SOURCE_ID => 5,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => 3,
        ]);

        $this->assertSame(9, $filter->getTargetId(5, null, 'abc', 'yes', []));
    }

    public function testLookupFailureFallsBack(): void
    {
        $manager = $this->createMock(SubmissionManager::class);
        $manager->method('find')->willThrowException(new \RuntimeException('db is down'));
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('get_current_blog_id')->willReturn(1);
        $filter = new TargetIdFilter($manager, $proxy, $this->makeCache());

        $this->assertSame(10, $filter->getTargetId(10));
        $this->assertNull($filter->getTargetId(10, '', null, false));
    }

    public function testResultsAreCached(): void
    {
        $filter = $this->makeFilter([$this->makeSubmission(55)]);

        $this->assertSame(55, $filter->getTargetId(10, 'post'));
        $this->assertSame(55, $filter->getTargetId(10, 'post'));
    }

    public function testMissesAreCached(): void
    {
        $filter = $this->makeFilter([]);

        $this->assertSame(10, $filter->getTargetId(10, 'post'));
        $this->assertNull($filter->getTargetId(10, 'post', null, false));
    }

    public function testCacheIsSharedBetweenInstancesAndKeyedByParameters(): void
    {
        $cache = $this->makeCache();
        $this->makeFilter([$this->makeSubmission(55)], null, 3, $cache)->getTargetId(10, 'post');

        $this->assertSame(55, $this->makeFilter([], null, 3, $cache, 0)->getTargetId(10, 'post'));
        $this->assertSame(10, $this->makeFilter([], null, 3, $cache, 1)->getTargetId(10, 'page'));
        $this->assertSame(10, $this->makeFilter([], null, 4, $cache, 1)->getTargetId(10, 'post'));
    }
}
