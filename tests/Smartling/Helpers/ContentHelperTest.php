<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class ContentHelperTest extends TestCase
{
    public function setUp() {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }
    /**
     * @dataProvider providerCheckEntityExists
     * @param int $currentBlogId
     * @param int $otherBlogId
     * @param bool $exists
     */
    public function testCheckEntityExists($currentBlogId, $otherBlogId, $exists)
    {
        $x = $this->getMockBuilder(ContentHelper::class)->setMethods(['getIoFactory', 'getSiteHelper'])->getMock();

        $entity = $this->getMockBuilder(EntityAbstract::class)->setMethods(['get'])->getMockForAbstractClass();
        $entity->method('get')->willReturnSelf();

        $ioFactory = $this->getMock(ContentEntitiesIOFactory::class);
        if ($exists) {
            $ioFactory->method('getMapper')->willReturn($entity);
        } else {
            $ioFactory->method('getMapper')->willThrowException(new EntityNotFoundException());
        }

        $siteHelper = $this->getMock(SiteHelper::class);
        $siteHelper->method('getCurrentBlogId')->willReturn($currentBlogId);
        $siteHelper->expects($currentBlogId === $otherBlogId ? self::never() : self::once())->method('switchBlogId')->with($otherBlogId);
        $siteHelper->expects($currentBlogId === $otherBlogId ? self::never() : self::once())->method('restoreBlogId');

        $x->method('getIoFactory')->willReturn($ioFactory);
        $x->method('getSiteHelper')->willReturn($siteHelper);

        self::assertEquals($exists, $x->checkEntityExists($otherBlogId, 'post', 3));
    }

    public function providerCheckEntityExists() {
        return [
            [1, 2, true],
            [1, 2, false],
            [1, 1, true],
            [1, 1, false],
        ];
    }

    public function testRemoveUnlockedTargetMetadata()
    {
        $targetId = 1;
        $proxy = $this->getMock(WordpressFunctionProxyHelper::class);
        $proxy->expects(self::once())->method('delete_post_meta')->with($targetId, 'locked_field')->willReturn(true);

        $entity = $this->getMockBuilder(EntityAbstract::class)->setMethods(['get', 'getMetadata', 'toArray'])->getMockForAbstractClass();
        $entity->method('get')->willReturnSelf();
        $entity->method('getMetadata')->willReturn(['locked_field' => 'lockedFieldValue', 'unlocked_field' => 'unlockedFieldValue']);

        $handler = $this->getMock(PostEntityStd::class);
        $handler->method('get')->willReturn($entity);

        $ioFactory = $this->getMock(ContentEntitiesIOFactory::class);
        $ioFactory->method('getHandler')->willReturn($handler);

        $x = $this->getMockBuilder(ContentHelper::class)->setMethods(['getIoFactory', 'getSiteHelper'])->setConstructorArgs([$proxy])->getMock();
        $x->method('getIoFactory')->willReturn($ioFactory);
        $x->method('getSiteHelper')->willReturn($this->getMock(SiteHelper::class));

        /**
         * @var SubmissionEntity|\PHPUnit_Framework_MockObject_MockObject $submission
         */
        $submission = $this->getMockBuilder(SubmissionEntity::class)->setMethods(['getContentType', 'getLockedFields', 'getTargetId'])->getMockForAbstractClass();
        $submission->method('getContentType')->willReturn('post');
        $submission->method('getTargetId')->willReturn(1);
        $submission->method('getLockedFields')->willReturn(['entity/post_title', 'meta/locked_field']);

        $x->removeUnlockedTargetMetadata($submission);
    }
}
