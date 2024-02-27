<?php

namespace Smartling\Tests\Smartling\Helpers;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Smartling\DbAl\Migrations\Migration160125;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\PlaceholderHelper;
use Smartling\Helpers\ShortcodeHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Tests\Traits\InvokeMethodTrait;

class ShortcodeHelperTest extends TestCase
{
    use InvokeMethodTrait;

    /**
     * @dataProvider getCDATADataProvider
     */
    public function testGetCdata(string $expectedString, \DOMNode $node)
    {
        self::assertEquals($expectedString, ShortcodeHelper::getCdata($node));
    }

    /**
     * @covers  ShortcodeHelper::extractTranslations
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

        $helper = $this->getShortcodeHelper();

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

    public function testProcessTranslation()
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('apply_filters')->willReturnArgument(1);

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->loadXML('<test><string name="entity/post_content"><shortcodeattribute shortcode="custom-shortcode" hash="d3f06f0519040b5d46e6160b1a1e4d71" name="name"><![CDATA[split-content-partner-logos]]></shortcodeattribute><![CDATA[<div>#sl-start#[custom-shortcode name="split-content-partner-logos"]#sl-end#</div>]]></string></test>');

        $parameters = new TranslationStringFilterParameters();
        $parameters->setDom($xml);
        $parameters->setNode((new \DOMXPath($xml))->query('/test/string')->item(0));

        $this->assertEquals(
            '<div>[custom-shortcode name="split-content-partner-logos"]</div>',
            $this->getShortcodeHelper($wpProxy)->processTranslation($parameters)->getNode()->nodeValue,
        );
    }

    /**
     * @dataProvider buildShortcodeAttributesDataProvider
     */
    public function testBuildShortcodeAttributes(array $attributes, string $expectedAttributeString)
    {
        self::assertEquals($expectedAttributeString,
            $this->invokeMethod($this->getShortcodeHelper(), 'buildShortcodeAttributes', [$attributes]));
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
     * @dataProvider buildShortcodeDataProvider
     */
    public function testBuildShortcode(string $name, array $attributes, string $content, string $openString, string $closeString, string $expectedString)
    {
        self::assertEquals($expectedString,
            $this->invokeMethod(
                $this->getShortcodeHelper(),
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
                PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_START . '[',
                ']' . PlaceholderHelper::SMARTLING_PLACEHOLDER_MASK_END,
                '#sl-start#[vc_row a="b" c="d"]#sl-end#Row here#sl-start#[/vc_row]#sl-end#',
            ],
        ];
    }

    /**
     * @covers  ShortcodeHelper::uploadShortcodeHandler
     */
    public function testUploadShortcodeHandler()
    {
        $expectedXML = '<?xml version="1.0" encoding="UTF-8"?>
<data>
  <string name="entity/post_content"><shortcodeattribute shortcode="vc_row" hash="7dca36e91549bd307f2aed1257244f52" name="title_a"><![CDATA[Title A]]></shortcodeattribute><shortcodeattribute shortcode="vc_row" hash="2efe5cb3c5ea44c993aaaf19cf09158b" name="title_b"><![CDATA[Title B]]></shortcodeattribute><![CDATA[#sl-start#[vc_row title_a="Title A" title_b="Title B"]#sl-end#inner content#sl-start#[/vc_row]#sl-end#]]></string>
</data>
';

        /**
         * Source XML for test
         */
        $xml_raw = '<?xml version="1.0" encoding="UTF-8"?><data><string name="entity/post_content" /></data>';
        $xml     = new DOMDocument('1.0', 'UTF-8');
        $xml->loadXML($xml_raw);
        $xPath    = new \DOMXPath($xml);
        $nodeList = $xPath->query('/data/string');
        $node     = $nodeList->item(0);

        $helper = $this->getShortcodeHelper();

        $name = 'vc_row';

        $attributes = [
            'title_a' => 'Title A',
            'title_b' => 'Title B',
        ];

        $content = 'inner content';


        /**
         * [vc_row title_a="Title A" title_b="Title B"]inner content[/vc_row]
         */
        $shortcode = $this->invokeStaticMethod(
            get_class($helper),
            'buildShortcode',
            [
                $name,
                $attributes,
                $content,
            ]
        );

        $node->appendChild(new \DOMCdataSection($shortcode));

        $mock = $this
            ->getMockBuilder(ShortcodeHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['preUploadFiltering'])
            ->getMock();

        $mock->expects(self::once())
             ->method('preUploadFiltering')
             ->with($name, $attributes)
             ->willReturnArgument(1);

        $param = new TranslationStringFilterParameters();
        $param->setDom($xml);
        $param->setNode($node);

        $mock->setParams($param);
        $maskedShortcode = $mock->uploadShortcodeHandler($attributes, $content, $name);

        ShortcodeHelper::replaceCData($node, $maskedShortcode);

        $xml->preserveWhiteSpace = true;
        $xml->formatOutput       = true;
        $result                  = $xml->saveXML();

        self::assertEquals($expectedXML, $result);
    }

    /**
     * @covers  ShortcodeHelper::shortcodeApplierHandler
     */
    public function testShortcodeApplierHandler()
    {
        $sourceXML   = '<?xml version="1.0" encoding="UTF-8"?>
<data>
  <string name="entity/post_content"><shortcodeattribute shortcode="vc_row" hash="7dca36e91549bd307f2aed1257244f52" name="title_a"><![CDATA[Title A Translated]]></shortcodeattribute><shortcodeattribute shortcode="vc_row" hash="2efe5cb3c5ea44c993aaaf19cf09158b" name="title_b"><![CDATA[Title B Translated]]></shortcodeattribute><![CDATA[#sl-start#[vc_row title_a="Title A" title_b="Title B"]#sl-end#inner content#sl-start#[/vc_row]#sl-end#]]></string>
</data>
';
        $expectedXML = '[vc_row title_a="Title A Translated" title_b="Title B Translated"]inner content[/vc_row]';

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->loadXML($sourceXML);
        $xPath    = new \DOMXPath($xml);
        $nodeList = $xPath->query('/data/string');
        $node     = $nodeList->item(0);

        $name       = 'vc_row';
        $attributes = [
            'title_a' => 'Title A Translated',
            'title_b' => 'Title B Translated',
        ];
        $content    = 'inner content';

        $mock = $this
            ->getMockBuilder(ShortcodeHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['passPostDownloadFilters'])
            ->getMock();

        $param = new TranslationStringFilterParameters();
        $param->setDom($xml);
        $param->setNode($node);

        $mock->setParams($param);

        $mock->extractTranslations($node);
        $mock->unmask();

        $mock->expects(self::once())
             ->method('passPostDownloadFilters')
             ->with($name, $attributes)
             ->willReturnArgument(1);

        $result = $mock->shortcodeApplierHandler($attributes, $content, $name);

        self::assertEquals($expectedXML, $result);
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

    private function getShortcodeHelper(WordpressFunctionProxyHelper $wpProxy = null): ShortcodeHelper
    {
        if ($wpProxy === null) {
            $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        }
        return new ShortcodeHelper(
            $this->createMock(ContentSerializationHelper::class),
            $this->createMock(FieldsFilterHelper::class),
            new PlaceholderHelper(),
            $this->createMock(SettingsManager::class),
            $wpProxy,
        );
    }
}
