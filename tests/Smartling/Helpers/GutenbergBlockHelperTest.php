<?php

namespace {
    if (!function_exists('wp_parse_str')) {
        /**
         * @param string $string
         * @param array $array
         */
        function wp_parse_str($string, &$array)
        {
            parse_str($string, $array);
            $array = apply_filters('wp_parse_str', $array);
        }
    }
    if (!function_exists('wp_parse_args'))
    {
        /**
         * @param string|array|object $args
         * @param array $defaults
         * @return array
         */
        function wp_parse_args($args, $defaults = '')
        {
            if (is_object($args)) {
                $parsed_args = get_object_vars($args);
            } elseif (is_array($args)) {
                $parsed_args =& $args;
            } else {
                wp_parse_str($args, $parsed_args);
            }

            if (is_array($defaults)) {
                return array_merge($defaults, $parsed_args);
            }
            return $parsed_args;
        }
    }
    require __DIR__ . '/../../wordpressBlocks.php';
}

namespace Smartling\Tests\Smartling\Helpers {

    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SettingsManagerMock;

class GutenbergBlockHelperTest extends TestCase
{
    use InvokeMethodTrait;
    use SettingsManagerMock;

    /**
     * @param array $methods
     * @return MockObject|GutenbergBlockHelper
     */
    private function mockHelper($methods = ['getLogger', 'postReceiveFiltering', 'preSendFiltering', 'processAttributes'])
    {
        return $this->createPartialMock(GutenbergBlockHelper::class, $methods);
    }

    public $helper;

    protected function setUp(): void
    {
        $this->helper = new GutenbergBlockHelper();
    }

    public function testAddPostContentBlocksWithBlocks()
    {
        $blocks = [
            <<<HTML
<!-- wp:media-text {"mediaId":55,"mediaLink":"http://localhost.localdomain/2020/02/26/test/abc-teachers/","mediaType":"image"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="http://localhost.localdomain/wp-content/uploads/2020/02/abc-teachers.jpg" alt="" class="wp-image-55"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph {"placeholder":"Content…","fontSize":"large"} -->
<p class="has-large-font-size">Some text</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
HTML
        ,
            <<<HTML
<!-- wp:image {"id":55,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="http://localhost.localdomain/wp-content/uploads/2020/02/abc-teachers.jpg" alt="" class="wp-image-55"/></figure>
<!-- /wp:image -->
HTML
        ,
        ];
        $x = new GutenbergBlockHelper();
        $postContent = $blocks[0] . '<p>Wee, I\'m not a part of Gutenberg!</p>' . $blocks[1];
        $result = $x->addPostContentBlocks(['post_content' => $postContent]);
        $this->assertCount(3, $result);
        $this->assertEquals($postContent, $result['post_content'], 'Content should not change');
        $this->assertStringStartsWith('<!-- wp:media-text', $result['post_content/blocks/0']);
        $this->assertEquals($blocks[1], $result['post_content/blocks/1']);
    }

    public function testAddPostContentBlocksWithNoBlocks()
    {
        $x = new GutenbergBlockHelper();
        $postContent = '<!-- An html comment --><p>Some content</p><!-- Another comment -->';
        $result = $x->addPostContentBlocks(['post_content' => $postContent]);
        $this->assertCount(1, $result);
        $this->assertEquals($postContent, $result['post_content']);
    }

    public function testRegisterFilters()
    {
        $result = $this->helper->registerFilters([]);
        $expected = [
            ['pattern' => '^type$', 'action' => 'copy'],
            ['pattern' => '^providerNameSlug$', 'action' => 'copy'],
            ['pattern' => '^align$', 'action' => 'copy'],
            ['pattern' => '^className$', 'action' => 'copy'],
        ];
        self::assertEquals($expected, $result);
    }

    /**
     * @param string $blockName
     * @param array $flatAttributes
     * @param array $postFilterMock
     * @param array $preFilterMock
     * @dataProvider processAttributesDataProvider
     */
    public function testProcessAttributes(?string $blockName, array $flatAttributes, array $postFilterMock, array $preFilterMock)
    {
        $helper = $this->mockHelper(['getLogger', 'postReceiveFiltering', 'preSendFiltering']);

        $helper
            ->method('postReceiveFiltering')
            ->with($flatAttributes)
            ->willReturn($postFilterMock);

        $helper
            ->method('preSendFiltering')
            ->with($flatAttributes)
            ->willReturn($preFilterMock);

        $result = $helper->processAttributes($blockName, $flatAttributes);

        self::assertEquals($preFilterMock, $result);

    }

    public function processAttributesDataProvider(): array
    {
        return [
            'plain' => [
                null,
                [],
                [],
                [],
            ],
            'empty' => ['block', [], [], [],],
            'simple' => [
                'block',
                ['a/0' => 'first', 'a/1' => 'second', 'a/2/0' => '5',],
                ['a/0' => 'first', 'a/1' => 'second', 'a/2/0' => '6',],
                ['a/0' => 'first', 'a/1' => 'second',],
            ],
        ];
    }

    /**
     * @dataProvider hasBlocksDataProvider
     * @param string $sample
     * @param bool $expectedResult
     */
    public function testHasBlocks(string $sample, bool $expectedResult)
    {
        self::assertEquals($expectedResult, $this->helper->hasBlocks($sample));
    }

    public function hasBlocksDataProvider(): array
    {
        return [
            'simple text' => ['lorem ipsum dolor', false],
            'block with 1 space' => ['lorem <!-- wp:ipsum dolor', true],
            'block with several spaces' => ['lorem <!--  wp:ipsum dolor', true],
        ];
    }

    public function testPackData()
    {
        $sample = ['foo' => 'bar'];
        $expected = base64_encode(serialize($sample));
        $result = $this->invokeMethod($this->helper, 'packData', [$sample]);
        self::assertEquals($expected, $result);
    }

    public function testUnpackData()
    {
        $sample = ['foo' => 'bar'];
        $source = base64_encode(serialize($sample));
        $result = $this->invokeMethod($this->helper, 'unpackData', [$source]);
        self::assertEquals($sample, $result);
    }

    public function testPackUnpack()
    {
        $sample = ['foo' => 'bar'];
        $processed = $this->invokeMethod(
            $this->helper,
            'unpackData',
            [
                $this->invokeMethod(
                    $this->helper,
                    'packData',
                    [
                        $sample,
                    ]
                ),
            ]
        );
        self::assertEquals($processed, $sample);
    }

    /**
     * @param array $block
     * @param string $expected
     * @dataProvider placeBlockDataProvider
     */
    public function testPlaceBlock(array $block, string $expected)
    {
        $helper = $this->mockHelper();
        $params = new TranslationStringFilterParameters();
        $params->setDom(new \DOMDocument('1.0', 'utf8'));

        $helper->setParams($params);
        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock(), $this->getAcfDynamicSupportMock()));
        $helper
               ->method('processAttributes')
               ->willReturnArgument(1);

        $result = $this->invokeMethod($helper, 'placeBlock', [$block]);
        $xmlNodeRendered = $params->getDom()->saveXML($result);
        self::assertEquals($expected, $xmlNodeRendered);
    }

    public function placeBlockDataProvider(): array
    {
        return [
            'no nested' => [
                [
                    'blockName' => 'test',
                    'attrs' => [
                        'foo' => 'bar',
                    ],
                    'innerContent' => [
                        'chunk a',
                        'chunk b',
                        'chunk c',
                    ],
                ],
                '<gutenbergBlock blockName="test" originalAttributes="YToxOntzOjM6ImZvbyI7czozOiJiYXIiO30="><![CDATA[]]><contentChunk><![CDATA[chunk a]]></contentChunk><contentChunk><![CDATA[chunk b]]></contentChunk><contentChunk><![CDATA[chunk c]]></contentChunk><blockAttribute name="foo"><![CDATA[bar]]></blockAttribute></gutenbergBlock>',
            ],
            'nested block' => [
                [
                    'blockName' => 'test',
                    'attrs' => [
                        'foo' => 'bar',
                    ],
                    'innerBlocks' => [
                        [
                            'blockName' => 'test1',
                            'attrs' => [
                                'bar' => 'foo',
                            ],
                            'innerContent' => [
                                'chunk d',
                                'chunk e',
                                'chunk f',
                            ],
                        ],
                    ],
                    'innerContent' => [
                        'chunk a',
                        null,
                        'chunk c',
                    ],
                ],
                '<gutenbergBlock blockName="test" originalAttributes="YToxOntzOjM6ImZvbyI7czozOiJiYXIiO30="><![CDATA[]]><contentChunk><![CDATA[chunk a]]></contentChunk><gutenbergBlock blockName="test1" originalAttributes="YToxOntzOjM6ImJhciI7czozOiJmb28iO30="><![CDATA[]]><contentChunk><![CDATA[chunk d]]></contentChunk><contentChunk><![CDATA[chunk e]]></contentChunk><contentChunk><![CDATA[chunk f]]></contentChunk><blockAttribute name="bar"><![CDATA[foo]]></blockAttribute></gutenbergBlock><contentChunk><![CDATA[chunk c]]></contentChunk><blockAttribute name="foo"><![CDATA[bar]]></blockAttribute></gutenbergBlock>',
            ],
        ];
    }

    /**
     * @param string $blockName
     * @param array  $attributes
     * @param array  $chunks
     * @param string $expected
     * @dataProvider renderGutenbergBlockDataProvider
     */
    public function testRenderGutenbergBlock(string $blockName, array $attributes, array $chunks, string $expected)
    {
        self::assertEquals($expected, $this->helper->renderGutenbergBlock($blockName, $attributes, $chunks));
    }

    public function renderGutenbergBlockDataProvider(): array
    {
        return [
            'inline' => [
                'inline',
                [
                    'a' => 'b',
                    'c' => 'd',
                ],
                [],
                '<!-- wp:inline {"a":"b","c":"d"} /-->',
            ],
            'block' => [
                'block',
                [
                    'a' => 'b',
                    'c' => 'd',
                ],
                [
                    'some',
                    ' ',
                    'chunks',

                ],
                '<!-- wp:block {"a":"b","c":"d"} -->some chunks<!-- /wp:block -->',
            ],
            'accents' => [
                'acf/sticky-cta',
                [
                    'id' => 'block_5e46fa29a5a8e',
                    'name' => 'acf/sticky-cta',
                    'data' =>
                        [
                            'copy' => 'Pronto para reservar seu próximo evento?',
                            'cta_copy' => 'Obter uma cotação',
                            'cta_url' => 'https://www.test.com/somePath',
                            'sticky_behavior' => 'bottom',
                        ],
                    'align' => '',
                    'mode' => 'auto',
                ],
                [],
                '<!-- wp:acf/sticky-cta {"id":"block_5e46fa29a5a8e","name":"acf\/sticky-cta",' .
                '"data":{"copy":"Pronto para reservar seu próximo evento?","cta_copy":"Obter uma cotação"' .
                ',"cta_url":"https:\/\/www.test.com\/somePath","sticky_behavior":"bottom"},' .
                '"align":"","mode":"auto"} /-->'
            ],
            'emojis' => [
                'acf/test',
                ['data' => ['copy' => 'Test 𝒞 and 😂, 絵文字, 👩‍🦽, ⚛️.']],
                [],
                '<!-- wp:acf/test {"data":{"copy":"Test 𝒞 and 😂, 絵文字, 👩‍🦽, ⚛️."}} /-->'
            ],
            'pre-encoded' => [
                'acf/test',
                ['data' => ['copy' => "Pronto para reservar seu pr\\u00f3ximo evento?"]],
                [],
                '<!-- wp:acf/test {"data":{"copy":"Pronto para reservar seu pr\\\\u00f3ximo evento?"}} /-->'
            ],
            'quotes as html entities' => [
                'wework-blocks/geo-location',
                ['showList' => '[{&quot;value&quot;:&quot;united-states&quot;,&quot;label&quot;:&quot;United States&quot;}]'],
                [],
                '<!-- wp:wework-blocks/geo-location {\"showList\":\"[{\\\\\\"value\\\\\\":\\\\\\"united-states\\\\\\",\\\\\\"label\\\\\\":\\\\\\"United States\\\\\\"}]\"} /-->',
            ]
        ];
    }

    /**
     * @dataProvider processTranslationAttributesDataSource
     * @param string $blockName
     * @param array  $originalAttributes
     * @param array  $translatedAttributes
     * @param array  $expected
     */
    public function testProcessTranslationAttributes(string $blockName, array $originalAttributes, array $translatedAttributes, array $expected)
    {
        $helper = $this->mockHelper();

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock(), $this->getAcfDynamicSupportMock()));

        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);


        $result = $this->invokeMethod(
            $helper,
            'processTranslationAttributes',
            [
                $blockName,
                $originalAttributes,
                $translatedAttributes,
            ]
        );

        self::assertEquals($expected, $result);
    }

    public function processTranslationAttributesDataSource(): array
    {
        return [
            'structured attributes' => [
                'block',
                ['data' => ['texts' => ['foo', 'bar']]],
                [
                    'data/texts/0' => 'foo1',
                    'data/texts/1' => 'bar1',
                ],
                ['data' => ['texts' => ['foo1', 'bar1']]],
            ],
        ];
    }

    public function testRenderTranslatedBlockNode()
    {
        $xmlPart = '<gutenbergBlock blockName="core/foo" originalAttributes="YToxOntzOjQ6ImRhdGEiO2E6Mzp7czo2OiJ0ZXh0X2EiO3M6NzoiVGl0bGUgMSI7czo2OiJ0ZXh0X2IiO3M6NzoiVGl0bGUgMiI7czo1OiJ0ZXh0cyI7YToyOntpOjA7czo1OiJsb3JlbSI7aToxO3M6NToiaXBzdW0iO319fQ=="><![CDATA[]]><contentChunk hash="d3d67cc32ac556aae106e606357f449e"><![CDATA[<p>Inner HTML</p>]]></contentChunk><blockAttribute name="data/text_a" hash="90bc6d3874182275bd4cd88cbd734fe9"><![CDATA[Title 1]]></blockAttribute><blockAttribute name="data/text_b" hash="e4bb56dda4ecb60c34ccb89fd50506df"><![CDATA[Title 2]]></blockAttribute><blockAttribute name="data/texts/0" hash="d2e16e6ef52a45b7468f1da56bba1953"><![CDATA[lorem]]></blockAttribute><blockAttribute name="data/texts/1" hash="e78f5438b48b39bcbdea61b73679449d"><![CDATA[ipsum]]></blockAttribute></gutenbergBlock>';
        $expectedBlock = '<!-- wp:core/foo {"data":{"text_a":"Title 1","text_b":"Title 2","texts":["lorem","ipsum"]}} --><p>Inner HTML</p><!-- /wp:core/foo -->';

        $dom = new \DOMDocument('1.0', 'utf8');
        $dom->loadXML($xmlPart);
        $xpath = new \DOMXPath($dom);

        $list = $xpath->query('/gutenbergBlock');
        $node = $list->item(0);
        $helper = $this->mockHelper();

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock(), $this->getAcfDynamicSupportMock()));
        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);


        $result = $helper->renderTranslatedBlockNode($node);
        self::assertEquals($expectedBlock, $result);
    }

    public function testRenderTranslatedBlockNodeAttributeTypes()
    {
        $blockData = ["id" => 42, "boolean" => true];
        $dom = new \DOMDocument('1.0', 'utf8');
        $dom->loadXML('<gutenbergBlock blockName="core/foo" originalAttributes="' . base64_encode(serialize($blockData)) . '"><![CDATA[]]></gutenbergBlock>');
        $node = $dom->childNodes->item(0);

        $helper = $this->mockHelper();
        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock(), $this->getAcfDynamicSupportMock()));
        $helper->method('postReceiveFiltering')->willReturnArgument(0);

        self::assertEquals(
            '<!-- wp:core/foo ' . json_encode($blockData) . ' /-->',
            $helper->renderTranslatedBlockNode($node)
        );
    }

    public function testSortChildNodesContent()
    {
        $dom = new \DOMDocument('1.0', 'utf8');

        $createElement = function ($name, array $attributes = [], $cdata = null) use ($dom) {
            $element = $dom->createElement($name);
            foreach ($attributes as $attrName => $attrValue) {
                $element->setAttributeNode(new \DOMAttr($attrName, $attrValue));
            }
            if (null !== $cdata) {
                $element->appendChild(new \DOMCdataSection($cdata));
            }
            return $element;
        };

        $node = $createElement('gutenbergBlock', ['blockName' => 'block']);
        $node->appendChild($createElement('contentChunk', [], 'chunk a'));
        $node->appendChild($createElement('contentChunk', [], 'chunk b'));
        $node->appendChild($createElement('contentChunk', [], 'chunk c'));
        $node->appendChild($createElement('blockAttribute', ['name' => 'attr_a'], 'attr a'));
        $node->appendChild($createElement('blockAttribute', ['name' => 'attr_b'], 'attr b'));
        $node->appendChild($createElement('blockAttribute', ['name' => 'attr_c'], 'attr c'));
        $node->appendChild($createElement('blockAttribute', ['name' => 'attr_d'], 'attr d'));

        $expected = [
            'chunks' => ['chunk a', 'chunk b', 'chunk c'],
            'attributes' => ['attr_a' => 'attr a', 'attr_b' => 'attr b', 'attr_c' => 'attr c', 'attr_d' => 'attr d'],
        ];
        $helper = $this->mockHelper(['getLogger', 'postReceiveFiltering']);
        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock(), $this->getAcfDynamicSupportMock()));
        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);

        $result = $helper->sortChildNodesContent($node);
        self::assertEquals($expected, $result);
    }

    /**
     * @dataProvider processStringDataProvider
     * @param string $contentString
     * @param int $parseCount
     * @param array $parseResult
     * @param string $expectedString
     */
    public function testProcessString(string $contentString, int $parseCount, array $parseResult, string $expectedString)
    {
        $sourceString = vsprintf('<string name="entity/post_content"><![CDATA[%s]]></string>', [$contentString]);
        $dom = new \DOMDocument('1.0', 'uft8');
        $dom->loadXML($sourceString);
        $node = $dom->getElementsByTagName('string')->item(0);

        $params = new TranslationStringFilterParameters();
        $params->setDom($dom);
        $params->setFilterSettings([]);
        $params->setSubmission(new SubmissionEntity());
        $params->setNode($node);


        $helper = $this->mockHelper(['getLogger', 'postReceiveFiltering', 'preSendFiltering', 'parseBlocks']);

        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);
        $helper
               ->method('preSendFiltering')
               ->willReturnArgument(0);

        $helper->expects(self::exactly($parseCount))
               ->method('parseBlocks')
               ->with($contentString)
               ->willReturn($parseResult);

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock(), $this->getAcfDynamicSupportMock()));

        $result = $helper->processString($params);

        $xml = $dom->saveXML($result->getNode());

        self::assertEquals($expectedString, $xml);
    }

    public function processStringDataProvider(): array
    {
        return [
            'no blocks' => [
                'Hello World',
                0,
                [],
                '<string name="entity/post_content"><![CDATA[Hello World]]></string>',
            ],
            'with blocks' => [
                '<!-- wp:paragraph -->
<p>some par 1</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>some par 2</p>
<!-- /wp:paragraph -->',
                1,
                [
                    [
                        'blockName' => 'core/paragraph',
                        'attrs' => [],
                        'innerBlocks' => [],
                        'innerHTML' => '
some par 1

',
                        'innerContent' => [
                            0 => '
some par 1

',
                        ],
                    ],
                    [
                        'blockName' => null,
                        'attrs' => [],
                        'innerBlocks' => [],
                        'innerHTML' => ' ',
                        'innerContent' => [0 => ' ',],
                    ],
                    [
                        'blockName' => 'core/paragraph',
                        'attrs' => [],
                        'innerBlocks' => [],
                        'innerHTML' => '
some par 2

',
                        'innerContent' => [
                            0 => '
some par 2

',
                        ],
                    ],
                ],
                '<string name="entity/post_content"><gutenbergBlock blockName="core/paragraph" originalAttributes="YTowOnt9"><![CDATA[]]><contentChunk><![CDATA[
some par 1

]]></contentChunk></gutenbergBlock><gutenbergBlock blockName="" originalAttributes="YTowOnt9"><![CDATA[]]><contentChunk><![CDATA[ ]]></contentChunk></gutenbergBlock><gutenbergBlock blockName="core/paragraph" originalAttributes="YTowOnt9"><![CDATA[]]><contentChunk><![CDATA[
some par 2

]]></contentChunk></gutenbergBlock><![CDATA[]]></string>',
            ],
        ];
    }

    /**
     * @dataProvider processTranslationDataProvider
     * @param string $inXML
     * @param string $expectedXML
     */
    public function testProcessTranslation(string $inXML, $expectedXML)
    {

        $dom = new \DOMDocument('1.0', 'uft8');
        $dom->loadXML($inXML);
        $node = $dom->getElementsByTagName('string')->item(0);

        $params = new TranslationStringFilterParameters();
        $params->setDom($dom);
        $params->setFilterSettings([]);
        $params->setSubmission(new SubmissionEntity());
        $params->setNode($node);


        $helper = $this->mockHelper(['getLogger', 'postReceiveFiltering', 'preSendFiltering']);

        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);
        $helper
               ->method('preSendFiltering')
               ->willReturnArgument(0);

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock(), $this->getAcfDynamicSupportMock()));

        $result = $helper->processTranslation($params);

        $xml = $dom->saveXML($result->getNode());

        self::assertEquals($expectedXML, $xml);
    }

    /**
     * @return AcfDynamicSupport|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getAcfDynamicSupportMock()
    {
        return $this->getMockBuilder(AcfDynamicSupport::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * @return array
     */
    public function processTranslationDataProvider()
    {
        return [
            'no blocks' => [
                '<string name="entity/post_content"><![CDATA[Hello World]]></string>',
                '<string name="entity/post_content"><![CDATA[Hello World]]></string>',
            ],
            'with blocks' => [
                '<string name="entity/post_content"><gutenbergBlock blockName="core/paragraph" originalAttributes="YTowOnt9"><![CDATA[]]><contentChunk><![CDATA[
some par 1

]]></contentChunk></gutenbergBlock><gutenbergBlock blockName="" originalAttributes="YTowOnt9"><![CDATA[]]><contentChunk><![CDATA[ ]]></contentChunk></gutenbergBlock><gutenbergBlock blockName="core/paragraph" originalAttributes="YTowOnt9"><![CDATA[]]><contentChunk><![CDATA[
some par 2

]]></contentChunk></gutenbergBlock><![CDATA[]]></string>',

                '<string name="entity/post_content"><gutenbergBlock blockName="" originalAttributes="YTowOnt9"/><gutenbergBlock blockName="core/paragraph" originalAttributes="YTowOnt9"/><![CDATA[<!-- wp:core/paragraph -->
some par 1

<!-- /wp:core/paragraph --> <!-- wp:core/paragraph -->
some par 2

<!-- /wp:core/paragraph -->]]></string>',
            ],
        ];
    }
}
}
