<?php

namespace Smartling\Tests\Smartling\DbAl\WordpressContentEntities;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
use Smartling\Helpers\WidgetHelper;

class TestWidgetEntity extends WidgetEntity
{
    private $testMap;

    public function __construct($type, array $map)
    {
        $this->testMap = $map;
        parent::__construct($type);
    }

    public function buildMap()
    {
        $this->map = $this->testMap;
    }
}

class EntityAbstractTest extends TestCase
{
    public function testGetAll()
    {
        $x = new TestWidgetEntity('test', [
            $this->getWidgetHelperMock(3),
            $this->getWidgetHelperMock(4),
            $this->getWidgetHelperMock(5)
        ]);

        self::assertEquals([], $this->reduce($x->getAll()), 'Zero-like limit should result in an empty array');
        self::assertEquals([3, 4, 5], $this->reduce($x->getAll(5)), 'Limit greater than object count should return all items');
        self::assertEquals([3, 4], $this->reduce($x->getAll(2)), 'Limit should effectively limit objects');
        self::assertEquals([4, 5], $this->reduce($x->getAll(2, 1)), 'Offset should work with limit');
        self::assertEquals([5], $this->reduce($x->getAll(5, 2)), 'Offset should limit objects');
        self::assertEquals([], $this->reduce($x->getAll(5, 5)), 'Out of bounds conditions should result in an empty array');
    }

    /**
     * @param int $id
     * @return \PHPUnit_Framework_MockObject_MockObject|WidgetHelper
     */
    private function getWidgetHelperMock($id)
    {
        $mock = $this->getMockBuilder(WidgetHelper::class)->disableOriginalConstructor()->getMock();
        $mock->method('toArray')->willReturn(['id' => $id]);
        return $mock;
    }

    private function reduce(array $array)
    {
        return array_reduce($array, static function ($carry, $item) {
            $carry[] = $item->getId();
            return $carry;
        }, []);
    }
}
