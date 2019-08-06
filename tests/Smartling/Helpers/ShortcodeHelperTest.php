<?php

namespace Smartling\Tests\Smartling\Helpers;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Smartling\Helpers\ShortcodeHelper;
use Smartling\Tests\Traits\InvokeMethodTrait;

/**
 * Class XmlEncoder
 * @package Smartling\Tests\Smartling\Helpers
 * @covers  \Smartling\Helpers\ShortcodeHelper
 */
class ShortcodeHelperTest extends TestCase
{
    use InvokeMethodTrait;

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
     * @covers  \Smartling\Helpers\ShortcodeHelper::extractTranslations
     */
    public function testExtractTranslations()
    {
        /**
         * Complex XML from VC plugin
         */
        $xml_raw = '<?xml version="1.0" encoding="UTF-8"?>
<!-- smartling.translate_paths = data/string/ -->
<!-- smartling.string_format_paths = html : data/string/ -->
<!-- smartling.source_key_paths = data/{string.key} -->
<!-- smartling.variants_enabled = true -->
<!-- Smartling Wordpress Connector version: 1.10.14 -->
<!-- Wordpress installation host: localhost -->
<!--  smartling.placeholder_format_custom = (#sl-start#\[\/?[^\]]+\]#sl-end#)  -->
<data><string name="entity/post_content"><![CDATA[#sl-start#[vc_row section_padding="custom" padding_top_value="2" padding_bottom_value="2" bg_image_position="background-position-0-0"]#sl-end#
 #sl-start#[vc_column width="1/1"]#sl-end#
  #sl-start#[vc_row_inner tweaked_layout="tweaked-layout-enabled"]#sl-end#
   #sl-start#[vc_column_inner width="1/4"]#sl-end##sl-start#[/vc_column_inner]#sl-end#
   #sl-start#[vc_column_inner width="1/2"]#sl-end#
    [section_title section_title_class=&quot;section-title-default&quot;]Section[/section_title]
   #sl-start#[/vc_column_inner]#sl-end#
   #sl-start#[vc_column_inner width="1/4"]#sl-end#
   #sl-start#[/vc_column_inner]#sl-end#
  #sl-start#[/vc_row_inner]#sl-end#
  #sl-start#[vc_accordion]#sl-end#
   #sl-start#[vc_accordion_tab title="Question 1 ?"]#sl-end#
    #sl-start#[vc_column_text]#sl-end#Answer 1#sl-start#[/vc_column_text]#sl-end#
   #sl-start#[/vc_accordion_tab]#sl-end#
   #sl-start#[vc_accordion_tab title="Question 2?"]#sl-end#
    #sl-start#[vc_column_text]#sl-end#Answer 2#sl-start#[/vc_column_text]#sl-end#
   #sl-start#[/vc_accordion_tab]#sl-end#
   #sl-start#[vc_accordion_tab title="Question 3?"]#sl-end#
    #sl-start#[vc_column_text]#sl-end#Answer 3#sl-start#[/vc_column_text]#sl-end#
   #sl-start#[/vc_accordion_tab]#sl-end#
  #sl-start#[/vc_accordion]#sl-end#
 #sl-start#[/vc_column]#sl-end#
#sl-start#[/vc_row]#sl-end#]]>
<shortcodeattribute shortcode="vc_row" hash="8b9035807842a4e4dbe009f3f1478127" name="section_padding"><![CDATA[custom]]></shortcodeattribute>
<shortcodeattribute shortcode="vc_row" hash="886358e2444e32ba8220b5fccd738eb8" name="bg_image_position"><![CDATA[background-position-0-0]]></shortcodeattribute>
<shortcodeattribute shortcode="vc_row_inner" hash="92bffdc2392896e0a70792b6cc57f06d" name="tweaked_layout"><![CDATA[tweaked-layout-enabled]]></shortcodeattribute>
<shortcodeattribute shortcode="vc_accordion_tab" hash="7150a0261db9d31a6f14eb6d30cb56e3" name="title"><![CDATA[Question 1 ?]]></shortcodeattribute>
<shortcodeattribute shortcode="vc_accordion_tab" hash="d84b1bb52322578e080301593717cf07" name="title"><![CDATA[Question 2?]]></shortcodeattribute>
<shortcodeattribute shortcode="vc_accordion_tab" hash="db09dd113b52e35b2db0e911cd7e2341" name="title"><![CDATA[Question 3?]]></shortcodeattribute>
</string></data>';

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->loadXML($xml_raw);
        $xPath = new \DOMXPath($xml);

        $nodelist = $xPath->query('/data/string');

        $node = $nodelist->item(0);

        $helper = new ShortcodeHelper();

        $this->invokeMethod($helper, 'extractTranslations', [$node]);

        // extracting from last to first
        $expectedTranslations = [
            'vc_accordion_tab' =>
                [
                    'title' =>
                        [
                            'db09dd113b52e35b2db0e911cd7e2341' => 'Question 3?',
                            'd84b1bb52322578e080301593717cf07' => 'Question 2?',
                            '7150a0261db9d31a6f14eb6d30cb56e3' => 'Question 1 ?',
                        ],
                ],
            'vc_row_inner'     => ['tweaked_layout' => ['92bffdc2392896e0a70792b6cc57f06d' => 'tweaked-layout-enabled']],
            'vc_row'           =>
                [
                    'bg_image_position' => ['886358e2444e32ba8220b5fccd738eb8' => 'background-position-0-0'],
                    'section_padding'   => ['8b9035807842a4e4dbe009f3f1478127' => 'custom'],
                ],
        ];

        self::assertEquals($expectedTranslations, $this->getProperty($helper, 'blockAttributes'));
    }

    /**
     * @covers       \Smartling\Helpers\ShortcodeHelper::buildShortcodeAttributes
     * @dataProvider buildShortcodeAttributesDataProvider
     */
    public function testBuildShortcodeAttributes(array $attributes, $expectedAttributeString)
    {
        self::assertEquals($expectedAttributeString,
            $this->invokeMethod(new ShortcodeHelper(), 'buildShortcodeAttributes', [$attributes]));
    }

    public function buildShortcodeAttributesDataProvider()
    {
        return [
            'empty'        => [
                [],
                '',
            ],
            'simple'       => [
                [
                    'foo' => 'bar',
                    'bar' => 'foo',
                ],
                ' foo="bar" bar="foo"',
            ],
            'with integer' => [
                [
                    'foo' => 5,
                    'bar' => '6',
                ],
                ' foo=5 bar=6',
            ],
            'without name' => [
                [
                    'foo' => 5,
                    'bar' => '6',
                    0     => 'test',
                    1     => 9,
                ],
                ' foo=5 bar=6 "test" 9',
            ],
            'escaped'      => [
                [
                    'foo' => "\"som",
                    'bar' => '6',
                ],
                ' foo="&quot;som" bar=6',
            ],

        ];
    }

    /**
     * @covers       \Smartling\Helpers\ShortcodeHelper::buildShortcode
     * @dataProvider buildShortcodeDataProvider
     */
    public function testBuildShortcode($name, $attributes, $content, $openString, $closeString, $expectedString)
    {
        self::assertEquals($expectedString,
            $this->invokeMethod(
                new ShortcodeHelper(),
                'buildShortcode',
                [
                    $name,
                    $attributes,
                    $content,
                    $openString,
                    $closeString,
                ]
            )
        );
    }

    public function buildShortcodeDataProvider()
    {
        return [
            'simple' => [
                'vc_row',
                ['a' => 'b', 'c' => 'd'],
                'Row here',
                '[',
                ']',
                '[vc_row a="b" c="d"]Row here[/vc_row]',
            ],
            'masked' => [
                'vc_row',
                ['a' => 'b', 'c' => 'd'],
                'Row here',
                ShortcodeHelper::SMARTLING_SHORTCODE_MASK_S . '[',
                ']' . ShortcodeHelper::SMARTLING_SHORTCODE_MASK_E,
                '#sl-start#[vc_row a="b" c="d"]#sl-end#Row here#sl-start#[/vc_row]#sl-end#',
            ],
        ];
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
