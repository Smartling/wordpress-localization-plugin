<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;

/**
 * Class XmlEncoder
 * @package Smartling\Tests\Smartling\Helpers
 * @covers  \Smartling\Helpers\ShortcodeHelper
 */
class ShortcodeHelperTest extends TestCase
{
    /**
     * @covers       \Smartling\Helpers\ShortcodeHelper::getCdata
     * @dataProvider getCDATADataProvider
     *
     * @param string   $expectedString
     * @param \DOMNode $node
     */
    public function testGetCdata($expectedString, \DOMNode $node)
    {
        self::assertEquals($expectedString, \Smartling\Helpers\ShortcodeHelper::getCdata($node));
    }

    /**
     * Creates \DOMNode with children CDATA with content from $parts
     *
     * @param array $parts
     *
     * @return \DOMNode
     */
    private function makeDomNode(array $parts)
    {
        $node = (new \DOMDocument('1.0', 'utf-8'))
            ->createElement('string');

        foreach ($parts as $part) {
            $node->appendChild(new \DOMCdataSection((string)$part));
        }

        return $node;
    }

    private function makeDataProviderSet(array $parts)
    {
        return [
            implode('', $parts),
            $this->makeDomNode($parts),
        ];
    }

    public function getCDATADataProvider()
    {
        $sets = [
            ['a', 'b', 'c'],
            ['strangeb', 'roken string'],
            ['one element'],
            [],
        ];
        $data = [];
        foreach ($sets as $set) {
            $data[] = $this->makeDataProviderSet($set);
        }

        return $data;
    }
}
