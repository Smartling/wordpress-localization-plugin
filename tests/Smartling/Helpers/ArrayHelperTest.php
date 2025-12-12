<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\ArrayHelper;
use Smartling\Settings\Locale;

class ArrayHelperTest extends TestCase
{
    public function testGetValueWithSimpleKey()
    {
        $array = ['key' => 'value'];
        $this->assertEquals('value', ArrayHelper::getValue($array, 'key'));
    }

    public function testGetValueWithDefaultValue()
    {
        $array = ['key' => 'value'];
        $this->assertEquals('default', ArrayHelper::getValue($array, 'missing', 'default'));
    }

    public function testGetValueWithDotNotation()
    {
        $array = ['level1' => ['level2' => ['level3' => 'value']]];
        $this->assertEquals('value', ArrayHelper::getValue($array, 'level1.level2.level3'));
    }

    public function testGetValueWithSlashSeparator()
    {
        $array = ['level1' => ['level2' => ['level3' => 'value']]];
        $this->assertEquals('value', ArrayHelper::getValue($array, 'level1/level2/level3', separator: '/'));
    }

    public function testGetValueWithClosure()
    {
        $array = ['name' => 'John'];
        $closure = static function($arr) { return $arr['name'] . ' Doe'; };
        $this->assertEquals('John Doe', ArrayHelper::getValue($array, $closure));
    }

    public function testGetValueWithObject()
    {
        $obj = (object)['property' => 'value'];
        $this->assertEquals('value', ArrayHelper::getValue($obj, 'property'));
    }

    public function testSetValue()
    {
        $helper = new ArrayHelper();
        $array = [];
        $result = $helper->setValue($array, 'key', 'value');
        $this->assertEquals(['key' => 'value'], $result);
    }

    public function testSetValueWithDotNotation()
    {
        $helper = new ArrayHelper();
        $array = [];
        $result = $helper->setValue($array, 'level1.level2.level3', 'value');
        $this->assertEquals(['level1' => ['level2' => ['level3' => 'value']]], $result);
    }

    public function testSetValueWithSlashSeparator()
    {
        $helper = new ArrayHelper();
        $array = [];
        $result = $helper->setValue($array, 'level1/level2/level3', 'value', '/');
        $this->assertEquals(['level1' => ['level2' => ['level3' => 'value']]], $result);
    }

    public function testRemove()
    {
        $array = ['key1' => 'value1', 'key2' => 'value2'];
        $removed = ArrayHelper::remove($array, 'key1');
        $this->assertEquals('value1', $removed);
        $this->assertEquals(['key2' => 'value2'], $array);
    }

    public function testRemoveWithDefault()
    {
        $array = ['key' => 'value'];
        $removed = ArrayHelper::remove($array, 'missing', 'default');
        $this->assertEquals('default', $removed);
    }

    public function testNotEmpty()
    {
        $this->assertTrue(ArrayHelper::notEmpty(['item']));
        $this->assertFalse(ArrayHelper::notEmpty([]));
        $this->assertFalse(ArrayHelper::notEmpty('string'));
    }

    public function testFlatten()
    {
        $helper = new ArrayHelper();
        $array = ['a' => ['b' => 'value']];
        $result = $helper->flatten($array);
        $this->assertEquals(['a/b' => 'value'], $result);
    }

    public function testFirst()
    {
        $this->assertEquals('first', ArrayHelper::first(['first', 'second']));
        $this->assertFalse(ArrayHelper::first([]));
    }

    public function testLast()
    {
        $this->assertEquals('last', ArrayHelper::last(['first', 'last']));
        $this->assertFalse(ArrayHelper::last([]));
    }

    public function testSortLocales()
    {
        $locale1 = $this->createMock(Locale::class);
        $locale1->method('getLabel')->willReturn('B');
        $locale2 = $this->createMock(Locale::class);
        $locale2->method('getLabel')->willReturn('A');

        $locales = [$locale1, $locale2];
        ArrayHelper::sortLocales($locales);

        $this->assertEquals('A', $locales[0]->getLabel());
        $this->assertEquals('B', $locales[1]->getLabel());
    }

    public function testStructurize()
    {
        $helper = new ArrayHelper();
        $array = ['a/b' => 'value'];
        $result = $helper->structurize($array);
        $this->assertEquals(['a' => ['b' => 'value']], $result);
    }

    public function testToArrayOfIntegers()
    {
        $result = ArrayHelper::toArrayOfIntegers(['1', '2', '3']);
        $this->assertEquals([1, 2, 3], $result);
    }

    public function testToArrayOfIntegersWithInvalidValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        ArrayHelper::toArrayOfIntegers(['1', 'invalid']);
    }

    public function testAdd()
    {
        $helper = new ArrayHelper();
        $result = $helper->add(['a' => 1], ['b' => 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }
}
