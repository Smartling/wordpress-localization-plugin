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
    if (!class_exists(WP_Block_Type::class)) {
        class WP_Block_Type
        {
            public $name;
            public $render_callback;
            public $attributes;
            public $editor_script;
            public $script;
            public $editor_style;
            public $style;

            /**
             * @param string $block_type
             * @param array|string $args
             */
            public function __construct($block_type, $args = array())
            {
                $this->name = $block_type;

                $this->set_props($args);
            }

            /**
             * @param array $attributes
             * @param string $content
             * @return string
             */
            public function render($attributes = array(), $content = '')
            {
                if (!$this->is_dynamic()) {
                    return '';
                }

                $attributes = $this->prepare_attributes_for_render($attributes);

                return (string)call_user_func($this->render_callback, $attributes, $content);
            }

            /**
             * @return boolean
             */
            public function is_dynamic()
            {
                return is_callable($this->render_callback);
            }

            /**
             * @param array $attributes
             * @return array
             */
            public function prepare_attributes_for_render($attributes)
            {
                if (!isset($this->attributes)) {
                    return $attributes;
                }

                foreach ($attributes as $attribute_name => $value) {
                    if (!isset($this->attributes[$attribute_name])) {
                        continue;
                    }

                    $schema = $this->attributes[$attribute_name];
                    $is_valid = rest_validate_value_from_schema($value, $schema);
                    if (is_wp_error($is_valid)) {
                        unset($attributes[$attribute_name]);
                    }
                }

                $missing_schema_attributes = array_diff_key($this->attributes, $attributes);
                foreach ($missing_schema_attributes as $attribute_name => $schema) {
                    if (isset($schema['default'])) {
                        $attributes[$attribute_name] = $schema['default'];
                    }
                }

                return $attributes;
            }

            /**
             * @param array|string $args
             */
            public function set_props($args)
            {
                $args = wp_parse_args($args,
                    [
                        'render_callback' => null,
                    ]
                );

                $args['name'] = $this->name;

                foreach ($args as $property_name => $property_value) {
                    $this->$property_name = $property_value;
                }
            }

            /**
             * @return array
             */
            public function get_attributes()
            {
                return is_array($this->attributes) ?
                    array_merge(
                        $this->attributes,
                        [
                            'layout' => [
                                'type' => 'string',
                            ],
                        ]
                    ) :
                    [
                        'layout' => [
                            'type' => 'string',
                        ],
                    ];
            }
        }
    }

    if (!class_exists(WP_Block_Type_Registry::class)) {
        class WP_Block_Type_Registry
        {
            private $registered_block_types = [];
            private static $instance = null;

            /**
             * @param string|WP_Block_Type $name
             * @param array $args
             * @return WP_Block_Type|false
             */
            public function register($name, array $args = [])
            {
                $block_type = null;
                if ($name instanceof WP_Block_Type) {
                    $block_type = $name;
                    $name = $block_type->name;
                }

                if (!is_string($name)) {
                    throw new RuntimeException('Block type names must be strings.');
                }

                if (preg_match('/[A-Z]+/', $name)) {
                    throw new RuntimeException('Block type names must not contain uppercase characters.');
                }

                $name_matcher = '/^[a-z0-9-]+\/[a-z0-9-]+$/';
                if (!preg_match($name_matcher, $name)) {
                    throw new RuntimeException('Block type names must contain a namespace prefix. Example: my-plugin/my-custom-block-type');
                }

                if ($this->is_registered($name)) {
                    throw new \RuntimeException(sprintf('Block type "%s" is already registered.', $name));
                }

                if (!$block_type) {
                    $block_type = new WP_Block_Type($name, $args);
                }

                $this->registered_block_types[$name] = $block_type;

                return $block_type;
            }

            /**
             * @param string|WP_Block_Type $name
             * @return WP_Block_Type|false
             */
            public function unregister($name)
            {
                if ($name instanceof WP_Block_Type) {
                    $name = $name->name;
                }

                if (!$this->is_registered($name)) {
                    throw new RuntimeException(sprintf('Block type "%s" is not registered.', $name));
                }

                $unregistered_block_type = $this->registered_block_types[$name];
                unset($this->registered_block_types[$name]);

                return $unregistered_block_type;
            }

            /**
             * @param string $name
             * @return WP_Block_Type|null
             */
            public function get_registered($name)
            {
                if (!$this->is_registered($name)) {
                    return null;
                }

                return $this->registered_block_types[$name];
            }

            /**
             * @return WP_Block_Type[]
             */
            public function get_all_registered()
            {
                return $this->registered_block_types;
            }

            /**
             * @param string $name
             * @return bool
             */
            public function is_registered($name)
            {
                return isset($this->registered_block_types[$name]);
            }

            /**
             * @return WP_Block_Type_Registry
             */
            public static function get_instance()
            {
                if (null === self::$instance) {
                    self::$instance = new self();
                }

                return self::$instance;
            }
        }
    }
}

namespace Smartling\Tests\Smartling\Helpers {

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SettingsManagerMock;

/**
 * Class GutenbergBlockHelperTest
 *
 * @package Smartling\Tests\Smartling\Helpers
 * @covers  \Smartling\Helpers\GutenbergBlockHelper
 */
class GutenbergBlockHelperTest extends TestCase
{
    use InvokeMethodTrait;
    use SettingsManagerMock;

    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|GutenbergBlockHelper
     */
    private function mockHelper($methods = ['postReceiveFiltering', 'preSendFiltering', 'processAttributes'])
    {
        return $this->getMockBuilder(GutenbergBlockHelper::class)
                    ->setMethods($methods)
                    ->getMock();
    }

    public $helper;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->helper = new GutenbergBlockHelper();
    }

    /**
     * @covers \Smartling\Helpers\GutenbergBlockHelper::registerFilters
     */
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
     * @param $blockName
     * @param $flatAttributes
     * @param $postFilterMock
     * @param $preFilterMock
     * @dataProvider processAttributesDataProvider
     * @covers       \Smartling\Helpers\GutenbergBlockHelper::processAttributes
     */
    public function testProcessAttributes($blockName, $flatAttributes, $postFilterMock, $preFilterMock)
    {
        $helper = $this->mockHelper(['postReceiveFiltering', 'preSendFiltering']);

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

    /**
     * @return array
     */
    public function processAttributesDataProvider()
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
     * @covers       \Smartling\Helpers\GutenbergBlockHelper::hasBlocks
     * @dataProvider hasBlocksDataProvider
     * @param string $sample
     * @param bool   $expectedResult
     * @throws \ReflectionException
     */
    public function testHasBlocks($sample, $expectedResult)
    {
        $result = $this->invokeMethod($this->helper, 'hasBlocks', [$sample]);
        self::assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function hasBlocksDataProvider()
    {
        return [
            'simple text' => ['lorem ipsum dolor', false],
            'block with 1 space' => ['lorem <!-- wp:ipsum dolor', true],
            'block with several spaces' => ['lorem <!--  wp:ipsum dolor', true],
        ];
    }

    /**
     * @throws \ReflectionException
     * @covers \Smartling\Helpers\GutenbergBlockHelper::packData
     */
    public function testPackData()
    {
        $sample = ['foo' => 'bar'];
        $expected = base64_encode(serialize($sample));
        $result = $this->invokeMethod($this->helper, 'packData', [$sample]);
        self::assertEquals($expected, $result);
    }

    /**
     * @throws \ReflectionException
     * @covers \Smartling\Helpers\GutenbergBlockHelper::unpackData
     */
    public function testUnpackData()
    {
        $sample = ['foo' => 'bar'];
        $source = base64_encode(serialize($sample));
        $result = $this->invokeMethod($this->helper, 'unpackData', [$source]);
        self::assertEquals($sample, $result);
    }

    /**
     * @throws \ReflectionException
     */
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
     * @param array  $block
     * @param string $expected
     * @throws \ReflectionException
     * @dataProvider placeBlockDataProvider
     * @covers       \Smartling\Helpers\GutenbergBlockHelper::placeBlock
     */
    public function testPlaceBlock($block, $expected)
    {
        $helper = $this->mockHelper();
        $params = new TranslationStringFilterParameters();
        $params->setDom(new \DOMDocument('1.0', 'utf8'));

        $helper->setParams($params);
        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock()));
        $helper
               ->method('processAttributes')
               ->willReturnArgument(1);

        $result = $this->invokeMethod($helper, 'placeBlock', [$block]);
        $xmlNodeRendered = $params->getDom()->saveXML($result);
        self::assertEquals($expected, $xmlNodeRendered);
    }

    /**
     * @return array
     */
    public function placeBlockDataProvider()
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
    public function testRenderGutenbergBlock($blockName, array $attributes, array $chunks, $expected)
    {
        self::assertEquals($expected, $this->helper->renderGutenbergBlock($blockName, $attributes, $chunks));
    }

    /**
     * @return array
     */
    public function renderGutenbergBlockDataProvider()
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
        ];
    }

    /**
     * @covers       \Smartling\Helpers\GutenbergBlockHelper::processTranslationAttributes
     * @dataProvider processTranslationAttributesDataSource
     * @param string $blockName
     * @param array  $originalAttributes
     * @param array  $translatedAttributes
     * @param array  $expected
     * @throws \ReflectionException
     */
    public function testProcessTranslationAttributes($blockName, $originalAttributes, $translatedAttributes, $expected)
    {
        $helper = $this->mockHelper();

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock()));

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

    /**
     * @return array
     */
    public function processTranslationAttributesDataSource()
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

    /**
     * @covers \Smartling\Helpers\GutenbergBlockHelper::renderTranslatedBlockNode
     */
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

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock()));
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
        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock()));
        $helper->method('postReceiveFiltering')->willReturnArgument(0);

        self::assertEquals(
            '<!-- wp:core/foo ' . json_encode($blockData) . ' /-->',
            $helper->renderTranslatedBlockNode($node)
        );
    }

    /**
     * @covers       \Smartling\Helpers\GutenbergBlockHelper::sortChildNodesContent
     */
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
        $helper = $this->mockHelper(['postReceiveFiltering']);
        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock()));
        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);

        $result = $helper->sortChildNodesContent($node);
        self::assertEquals($expected, $result);
    }

    /**
     * @covers       \Smartling\Helpers\GutenbergBlockHelper::processString
     * @dataProvider processStringDataProvider
     * @param string $contentString
     * @param int    $parseCount
     * @param array  $parseResult
     * @param string $expectedString
     */
    public function testProcessString($contentString, $parseCount, $parseResult, $expectedString)
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


        $helper = $this->mockHelper(['postReceiveFiltering', 'preSendFiltering', 'parseBlocks']);

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

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock()));

        $result = $helper->processString($params);

        $xml = $dom->saveXML($result->getNode());

        self::assertEquals($expectedString, $xml);
    }

    /**
     * @return array
     */
    public function processStringDataProvider()
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
     * @covers       \Smartling\Helpers\GutenbergBlockHelper::processTranslation
     * @dataProvider processTranslationDataProvider
     * @param string $inXML
     * @param string $expectedXML
     */
    public function testProcessTranslation($inXML, $expectedXML)
    {

        $dom = new \DOMDocument('1.0', 'uft8');
        $dom->loadXML($inXML);
        $node = $dom->getElementsByTagName('string')->item(0);

        $params = new TranslationStringFilterParameters();
        $params->setDom($dom);
        $params->setFilterSettings([]);
        $params->setSubmission(new SubmissionEntity());
        $params->setNode($node);


        $helper = $this->mockHelper(['postReceiveFiltering', 'preSendFiltering']);

        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);
        $helper
               ->method('preSendFiltering')
               ->willReturnArgument(0);

        $helper->setFieldsFilter(new FieldsFilterHelper($this->getSettingsManagerMock()));

        $result = $helper->processTranslation($params);

        $xml = $dom->saveXML($result->getNode());

        self::assertEquals($expectedXML, $xml);
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