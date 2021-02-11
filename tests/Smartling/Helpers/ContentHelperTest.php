<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class ContentHelperTest extends TestCase
{
    public function setUp(): void {
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
        $x = $this->getMockBuilder(ContentHelper::class)->setMethods(['getIoFactory', 'getSiteHelper'])->setConstructorArgs([new WordpressFunctionProxyHelper()])->getMock();

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
}
