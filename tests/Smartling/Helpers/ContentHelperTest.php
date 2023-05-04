<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\EntityHandler;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
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
    public function testCheckEntityExists(int $currentBlogId, int $otherBlogId, bool $exists)
    {
        $entity = $this->getMockBuilder(EntityAbstract::class)->onlyMethods(['get'])->getMockForAbstractClass();
        $entity->method('get')->willReturnSelf();

        $ioFactory = $this->createMock(ContentEntitiesIOFactory::class);
        if ($exists) {
            $ioFactory->method('getMapper')->willReturn($entity);
        } else {
            $ioFactory->method('getMapper')->willThrowException(new EntityNotFoundException());
        }

        $siteHelper = $this->createMock(SiteHelper::class);
        $siteHelper->method('getCurrentBlogId')->willReturn($currentBlogId);
        $siteHelper->expects($currentBlogId === $otherBlogId ? self::never() : self::once())->method('switchBlogId')->with($otherBlogId);
        $siteHelper->expects($currentBlogId === $otherBlogId ? self::never() : self::once())->method('restoreBlogId');

        $x = new ContentHelper($ioFactory, $siteHelper);

        self::assertEquals($exists, $x->checkEntityExists($otherBlogId, 'post', 3));
    }

    /**
     * @see https://bt.smartling.net/browse/WP-806
     */
    public function testReadSourceContentWithNullSubmissionId(): void
    {
        $entity1 = $this->createMock(Entity::class);
        $entity1->method('getTitle')->willReturn('Title 1');
        $entity2 = $this->createMock(Entity::class);
        $entity2->method('getTitle')->willReturn('Title 2');
        $entityHandler = $this->createMock(EntityHandler::class);
        $entityHandler->expects($this->exactly(2))->method('get')
            ->withConsecutive([1], [2])
            ->willReturnOnConsecutiveCalls($entity1, $entity2);
        $submission1 = $this->createMock(SubmissionEntity::class);
        $submission1->method('getId')->willReturn(null);
        $submission1->method('getSourceId')->willReturn(1);
        $submission2 = $this->createMock(SubmissionEntity::class);
        $submission2->method('getId')->willReturn(null);
        $submission2->method('getSourceId')->willReturn(2);
        $IOFactory = $this->createMock(ContentEntitiesIOFactory::class);
        $IOFactory->method('getHandler')->willReturn($entityHandler);
        $x = new ContentHelper($IOFactory, $this->createMock(SiteHelper::class));
        $this->assertEquals($entity1->getTitle(), $x->readSourceContent($submission1)->getTitle());
        $this->assertEquals($entity2->getTitle(), $x->readSourceContent($submission2)->getTitle());
    }

    public function providerCheckEntityExists(): array
    {
        return [
            [1, 2, true],
            [1, 2, false],
            [1, 1, true],
            [1, 1, false],
        ];
    }
}
