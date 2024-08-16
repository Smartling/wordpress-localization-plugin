<?php

namespace {
    require __DIR__ . '/../../wordpressBlocks.php';
    if (!function_exists('get_site_option')) {
        function get_site_option($storageKey, $defaultValue)
        {
            return $defaultValue;
        }
    }
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
    class ACFish_Data {
        public function __construct(public array $data = [])
        {
        }

        public function get_data() {
            return $this->data;
        }
    }

    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;
    use Smartling\Exception\EntityNotFoundException;
    use Smartling\Extensions\Acf\AcfDynamicSupport;
    use Smartling\Helpers\ContentSerializationHelper;
    use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
    use Smartling\Helpers\FieldsFilterHelper;
    use Smartling\Helpers\GutenbergBlockHelper;
    use Smartling\Helpers\GutenbergReplacementRule;
    use Smartling\Helpers\Serializers\SerializerJsonWithFallback;
    use Smartling\Helpers\SiteHelper;
    use Smartling\Helpers\WordpressFunctionProxyHelper;
    use Smartling\Models\GutenbergBlock;
    use Smartling\Replacers\ReplacerFactory;
    use Smartling\Replacers\ReplacerInterface;
    use Smartling\Settings\SettingsManager;
    use Smartling\Submissions\SubmissionEntity;
    use Smartling\Submissions\SubmissionManager;
    use Smartling\Tests\Traits\InvokeMethodTrait;
    use Smartling\Tests\Traits\SettingsManagerMock;
    use Smartling\Tuner\MediaAttachmentRulesManager;
    use Smartling\Vendor\Symfony\Component\Config\FileLocator;
    use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;
    use Smartling\Vendor\Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

    class GutenbergBlockHelperTest extends TestCase
{
    use InvokeMethodTrait;
    use SettingsManagerMock;

    private mixed $acfStores;

    /**
     * @return MockObject|GutenbergBlockHelper
     */
    private function mockHelper(
        array $methods = ['getLogger', 'postReceiveFiltering', 'preSendFiltering', 'processAttributes'],
        ?MediaAttachmentRulesManager $mediaAttachmentRulesManager = null,
        ?ReplacerFactory $replacerFactory = null
    )
    {
        if ($mediaAttachmentRulesManager === null) {
            $mediaAttachmentRulesManager = $this->createMock(MediaAttachmentRulesManager::class);
        }

        if ($replacerFactory === null) {
            $replacerFactory = $this->createMock(ReplacerFactory::class);
        }

        return $this->getMockBuilder(GutenbergBlockHelper::class)
            ->setConstructorArgs([
                $this->createMock(AcfDynamicSupport::class),
                $this->createMock(ContentSerializationHelper::class),
                $mediaAttachmentRulesManager,
                $replacerFactory,
                new SerializerJsonWithFallback(),
                $this->createMock(SettingsManager::class),
                $this->createMock(WordpressFunctionProxyHelper::class),
            ])
            ->onlyMethods($methods)
            ->getMock();
    }

    public GutenbergBlockHelper $helper;

    protected function setUp(): void
    {
        global $acf_stores;
        $this->acfStores = $acf_stores;
        $this->helper = new GutenbergBlockHelper(
            $this->createMock(AcfDynamicSupport::class),
            $this->createMock(ContentSerializationHelper::class),
            $this->createMock(MediaAttachmentRulesManager::class),
            $this->createMock(ReplacerFactory::class),
            new SerializerJsonWithFallback(),
            $this->createMock(SettingsManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        );
    }

    protected function tearDown(): void {
        global $acf_stores;
        $acf_stores = $this->acfStores;
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
        $helper->setFieldsFilter($this->getFieldsFilterHelper());
        $helper
               ->method('processAttributes')
               ->willReturnArgument(1);

        $result = $this->invokeMethod($helper, 'placeBlock', [GutenbergBlock::fromArray($block)]);
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
                '<gutenbergBlock blockName="test" originalAttributes="eyJmb28iOiJiYXIifQ=="><![CDATA[]]><contentChunk><![CDATA[chunk a]]></contentChunk><contentChunk><![CDATA[chunk b]]></contentChunk><contentChunk><![CDATA[chunk c]]></contentChunk><blockAttribute name="foo"><![CDATA[bar]]></blockAttribute></gutenbergBlock>',
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
                '<gutenbergBlock blockName="test" originalAttributes="eyJmb28iOiJiYXIifQ=="><![CDATA[]]><contentChunk><![CDATA[chunk a]]></contentChunk><gutenbergBlock blockName="test1" originalAttributes="eyJiYXIiOiJmb28ifQ=="><![CDATA[]]><contentChunk><![CDATA[chunk d]]></contentChunk><contentChunk><![CDATA[chunk e]]></contentChunk><contentChunk><![CDATA[chunk f]]></contentChunk><blockAttribute name="bar"><![CDATA[foo]]></blockAttribute></gutenbergBlock><contentChunk><![CDATA[chunk c]]></contentChunk><blockAttribute name="foo"><![CDATA[bar]]></blockAttribute></gutenbergBlock>',
            ],
        ];
    }

    /**
     * @dataProvider renderGutenbergBlockDataProvider
     */
    public function testRenderGutenbergBlock(string $blockName, array $attributes, array $chunks, string $expected, int $level = 0)
    {
        self::assertEquals($expected, $this->helper->renderGutenbergBlock($blockName, $attributes, $chunks, $level));
    }

    public function renderGutenbergBlockDataProvider(): array
    {
        $blockForNestedTest = json_decode('{"id":"block_5fe115ebd752d","name":"acf\/offset-impact","data":{"content":"<img class=\"alignnone size-medium wp-image-3000025130\" src=\"https:\/\/example.com\/ideas\/wp-content\/uploads\/sites\/4\/2020\/12\/GettyImages-1179050298_twitter-800x450.jpg\" alt=\"\" width=\"800\" height=\"450\" \/>","_content":"field_5d64117a9210b","media":"3000025132","_media":"field_5d64118f9210c","add_video_url":"","_add_video_url":"field_5eb3d3092a4ca"},"align":"","mode":"auto","wpClassName":"wp-block-acf-offset-impact"}', true);

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
                '<!-- wp:wework-blocks/geo-location {"showList":"[{\\\\\\"value\\\\\\":\\\\\\"united-states\\\\\\",\\\\\\"label\\\\\\":\\\\\\"United States\\\\\\"}]"} /-->',
            ],
            'nested attributes' => [
                'acf/image-text-wrap',
                ['data' => ['content' => '[caption id=\u0022attachment_3000029563\u0022]\n\nText', '_content' => 'More text']],
                [],
                '<!-- wp:acf/image-text-wrap {"data":{"content":"[caption id=\\\u0022attachment_3000029563\\\u0022]\\\n\\\nText","_content":"More text"}} /-->',
            ],
            'root block' => [
                'acf/offset-impact',
                $blockForNestedTest,
                [],
                '<!-- wp:acf/offset-impact {"id":"block_5fe115ebd752d","name":"acf\/offset-impact","data":{"content":"<img class=\"alignnone size-medium wp-image-3000025130\" src=\"https:\/\/example.com\/ideas\/wp-content\/uploads\/sites\/4\/2020\/12\/GettyImages-1179050298_twitter-800x450.jpg\" alt=\"\" width=\"800\" height=\"450\" \/>","_content":"field_5d64117a9210b","media":"3000025132","_media":"field_5d64118f9210c","add_video_url":"","_add_video_url":"field_5eb3d3092a4ca"},"align":"","mode":"auto","wpClassName":"wp-block-acf-offset-impact"} /-->',
            ],
            'nested block' => [
                'acf/offset-impact',
                $blockForNestedTest,
                [],
                '<!-- wp:acf/offset-impact {"id":"block_5fe115ebd752d","name":"acf\/offset-impact","data":{"content":"<img class=\"alignnone size-medium wp-image-3000025130\" src=\"https:\/\/example.com\/ideas\/wp-content\/uploads\/sites\/4\/2020\/12\/GettyImages-1179050298_twitter-800x450.jpg\" alt=\"\" width=\"800\" height=\"450\" \/>","_content":"field_5d64117a9210b","media":"3000025132","_media":"field_5d64118f9210c","add_video_url":"","_add_video_url":"field_5eb3d3092a4ca"},"align":"","mode":"auto","wpClassName":"wp-block-acf-offset-impact"} /-->',
                1,
            ],
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

        $helper->setFieldsFilter($this->getFieldsFilterHelper());

        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);


        $result = $this->invokeMethod(
            $helper,
            'processTranslationAttributes',
            [
                $this->createMock(SubmissionEntity::class),
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

    public function testTranslationAttributesWithRelations()
    {
        global $acf_stores; // validated in AcfDynamicSupport
        $acf_stores = [
            'local-fields' => new ACFish_Data(
                [['key' => 'field_6006a62721335', 'type' => 'image', 'name' => '', 'parent' => '']],
            ),
            'local-groups' => new ACFish_Data(),
        ];
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('get_post_types')->willReturn([
            'acf-field' => 'acf-field',
            'acf-field-group' => 'acf-field-group',
        ]);
        $acfDynamicSupport = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([
                $this->createMock(SettingsManager::class),
                $this->createMock(SiteHelper::class),
                $wpProxy,
            ])
            ->onlyMethods([
                'getReferencedTypeByKey',
                'validateAcfStores',
            ])
            ->getMock();
        $acfDynamicSupport->method('getReferencedTypeByKey')->willReturnCallback(static function(string $key): string {
            if ($key === 'field_6006a62721335') {
                return AcfDynamicSupport::REFERENCED_TYPE_MEDIA;
            }
            return AcfDynamicSupport::REFERENCED_TYPE_NONE;
        });
        $acfDynamicSupport->method('validateAcfStores')->willReturn(true);
        $replacer = $this->createMock(ReplacerInterface::class);
        $replacer->expects($this->exactly(2))->method('processAttributeOnDownload')->willReturnCallback(static function($originalValue) {
            $replacements = [
                19350 => 13,
                19351 => 17,
            ];
            if (array_key_exists($originalValue, $replacements)) {
                return $replacements[$originalValue];
            }

            return $originalValue;
        });
        $replacerFactory = $this->createMock(ReplacerFactory::class);
        $replacerFactory->method('getReplacer')->willReturnCallback(static function (string $id) use ($replacer): ReplacerInterface {
            if ($id === ReplacerFactory::REPLACER_RELATED) {
                return $replacer;
            }
            throw new EntityNotFoundException();
        });

        $x = new GutenbergBlockHelper(
            $acfDynamicSupport,
            $this->createMock(ContentSerializationHelper::class),
            new MediaAttachmentRulesManager(),
            $replacerFactory,
            $this->createMock(SerializerJsonWithFallback::class),
            $this->createMock(SettingsManager::class),
            new WordpressFunctionProxyHelper(),
        );
        $x->setFieldsFilter($this->createPartialMock(FieldsFilterHelper::class, []));
        $this->assertEquals([
            'data' => [
                'rows_0_image' => 17,
                '_rows_0_image' => 'field_6006a62721335',
                'rows_0_content' => 'translated content',
                'rows_1_image' => 13,
                '_rows_1_image' => 'field_6006a62721335',
                'rows_1_content' => 'other translated content',
            ]

        ], $x->applyDownloadRules('test/repeater', [
            'id' => 'block_629db415895a8',
            'name' => 'test/repeater',
            'data' => [
                'rows_0_type' => 'image',
                '_rows_0_type' => 'field_6006a6f2ede5b',
                'rows_0_image' => 19351,
                '_rows_0_image' => 'field_6006a62721335',
                'rows_0_content' => 'some content',
                '_rows_0_content' => 'field_5f722c9e1bc75',
                'rows_1_type' => 'image',
                '_rows_1_type' => 'field_6006a6f2ede5b',
                'rows_1_image' => 19350,
                '_rows_1_image' => 'field_6006a62721335',
                'rows_1_content' => 'other content',
                '_rows_1_content' => 'field_5f722c9e1bc75',
            ],
        ], [
            'data/rows_0_image' => '19351',
            'data/_rows_0_image' => 'field_6006a62721335',
            'data/rows_0_content' => 'translated content',
            'data/rows_1_image' => '19350',
            'data/_rows_1_image' => 'field_6006a62721335',
            'data/rows_1_content' => 'other translated content',
        ], $this->createMock(SubmissionEntity::class)));
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

        $helper->setFieldsFilter($this->getFieldsFilterHelper());
        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);

        $this->assertInstanceOf(\DOMElement::class, $node);
        $result = $helper->renderTranslatedBlockNode($node, $this->createMock(SubmissionEntity::class), 0);
        self::assertEquals($expectedBlock, $result);
    }

    public function testRenderTranslatedBlockNodeImageClass()
    {
        $sourceId = 17;
        $targetId = 21;
        $originalAttributes = base64_encode('{"id":' . $sourceId . ',"sizeSlug":"large","smartlingLockId":"tuzsc"}');
        $xmlPart = <<<XML
<gutenbergBlock blockName="core/image" originalAttributes="$originalAttributes"><contentChunk><![CDATA[
<figure class="wp-block-image size-large"><img src="http://test/wp-content/uploads/2021/11/imageClass.png" alt="" class="wp-image-$sourceId"/></figure>
]]></contentChunk><blockAttribute name="sizeSlug"><![CDATA[[l~árgé]]]></blockAttribute><blockAttribute name="smartlingLockId"><![CDATA[[t~úzsc]]]></blockAttribute></gutenbergBlock>
XML;
        $expectedBlock = '<!-- wp:core/image {"id":' . $targetId . ',"sizeSlug":"[l~árgé]","smartlingLockId":"[t~úzsc]"} -->
<figure class="wp-block-image size-large"><img src="http://test/wp-content/uploads/2021/11/imageClass.png" alt="" class="wp-image-' . $targetId . '"/></figure>
<!-- /wp:core/image -->';

        $dom = new \DOMDocument('1.0', 'utf8');
        $dom->loadXML($xmlPart);
        $xpath = new \DOMXPath($dom);

        $list = $xpath->query('/gutenbergBlock');
        $node = $list->item(0);

        $containerBuilder = new ContainerBuilder();
        $yamlFileLoader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $configDir = __DIR__ . '/../../../inc/config/';
        $yamlFileLoader->load($configDir . 'services.yml');
        $yamlFileLoader->load($configDir . 'media-attachment-rules.yml');
        $mediaAttachmentRulesManager = $containerBuilder->get('media.attachment.rules.manager');
        if (!$mediaAttachmentRulesManager instanceof MediaAttachmentRulesManager) {
            throw new \RuntimeException(MediaAttachmentRulesManager::class . ' expected');
        }

        $replacer = $this->createMock(ReplacerInterface::class);
        $replacer->method('processAttributeOnDownload')->willReturnCallback(function ($originalValue, $translatedValue) use ($sourceId, $targetId) {
            if ($originalValue === $sourceId) {
                return $targetId;
            }
            return $translatedValue;
        });
        $replacerFactory = $this->createMock(ReplacerFactory::class);
        $replacerFactory->method('getReplacer')->willReturn($replacer);

        $helper = $this->mockHelper(['postReceiveFiltering'], $mediaAttachmentRulesManager, $replacerFactory);

        $helper->setFieldsFilter($this->getFieldsFilterHelper());
        $helper->method('postReceiveFiltering')->willReturnArgument(0);

        $this->assertInstanceOf(\DOMElement::class, $node);
        $result = $helper->renderTranslatedBlockNode($node, $this->createMock(SubmissionEntity::class), 0);
        self::assertEquals($expectedBlock, $result, 'Expected both id attribute in Gutenberg block and inner image class to get replaced');
    }

    public function testRenderTranslatedBlockNodeAttributeTypes()
    {
        $blockData = ["id" => 42, "boolean" => true];
        $dom = new \DOMDocument('1.0', 'utf8');
        $dom->loadXML('<gutenbergBlock blockName="core/foo" originalAttributes="' . base64_encode(serialize($blockData)) . '"><![CDATA[]]></gutenbergBlock>');
        $node = $dom->childNodes->item(0);

        $helper = $this->mockHelper();
        $helper->setFieldsFilter($this->getFieldsFilterHelper());
        $helper->method('postReceiveFiltering')->willReturnArgument(0);

        self::assertEquals(
            '<!-- wp:core/foo ' . json_encode($blockData) . ' /-->',
            $helper->renderTranslatedBlockNode($node, $this->createMock(SubmissionEntity::class), 0),
        );
    }

    public function testReplacePreTranslateBlockContent()
    {
        $rulesManager = $this->createMock(MediaAttachmentRulesManager::class);
        $rulesManager->method('getGutenbergReplacementRules')->willReturn([
            new GutenbergReplacementRule('.+', 'smartlingLockId', ReplacerFactory::REPLACER_COPY),
        ]);
        $this->assertEquals(
            '',
            $this->getHelper($rulesManager, new ReplacerFactory($this->createMock(SubmissionManager::class)))
                ->replacePreTranslateBlockContent(new GutenbergBlock('paragraph', ['smartlingLockId' => 'lockValue'], [], '<p>Paragraph content</p>', []))
                ->getSmartlingLockId()
        );
    }

    public function testReplacePostTranslateBlockContent(): void
    {
        $this->fail(':(');
        $sourceId = 1;
        $targetId = 2;
        $sourceBlock = '<!-- wp:core/image {"id":' . $sourceId . ',"sizeSlug":"[l~árgé]","smartlingLockId":"[t~úzsc]"} -->
<figure class="wp-block-image size-large"><img src="http://test/wp-content/uploads/2021/11/imageClass.png" alt="" class="wp-image-' . $sourceId . '"/></figure>
<!-- /wp:core/image -->';
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getSourceId')->willReturn($sourceId);
        $submission->method('getTargetId')->willReturn($targetId);
        $containerBuilder = new ContainerBuilder();
        $yamlFileLoader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__));
        $configDir = __DIR__ . '/../../../inc/config/';
        $yamlFileLoader->load($configDir . 'services.yml');
        $yamlFileLoader->load($configDir . 'media-attachment-rules.yml');
        $mediaAttachmentRulesManager = $containerBuilder->get('media.attachment.rules.manager');
        if (!$mediaAttachmentRulesManager instanceof MediaAttachmentRulesManager) {
            throw new \RuntimeException(MediaAttachmentRulesManager::class . ' expected');
        }
        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('findOne')->willReturn($submission);
        $helper = new GutenbergBlockHelper(
            $this->createMock(AcfDynamicSupport::class),
            $this->createMock(ContentSerializationHelper::class),
            $mediaAttachmentRulesManager,
            new ReplacerFactory($submissionManager),
            new SerializerJsonWithFallback(),
            $this->createMock(SettingsManager::class),
            new WordpressFunctionProxyHelper(),
        );
        $this->assertStringContainsString("wp-image-$targetId", $helper->replacePostTranslateBlockContent($sourceBlock, $sourceBlock, $submission));
    }

    public function testSortChildNodesContent()
    {
        $dom = new \DOMDocument('1.0', 'utf8'); // This can't be moved inside closure

        $createElement = static function ($name, array $attributes = [], $cdata = null) use ($dom) {
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
        $helper->setFieldsFilter($this->getFieldsFilterHelper());
        $helper
               ->method('postReceiveFiltering')
               ->willReturnArgument(0);

        $result = $helper->sortChildNodesContent($node, $this->createMock(SubmissionEntity::class), 0);
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
        $parseResult = array_map(static function ($block) {
            return GutenbergBlock::fromArray($block);
        }, $parseResult);
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

        $helper->setFieldsFilter($this->getFieldsFilterHelper());

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
                '<string name="entity/post_content"><gutenbergBlock blockName="core/paragraph" originalAttributes="e30="><![CDATA[]]><contentChunk><![CDATA[
some par 1

]]></contentChunk></gutenbergBlock><gutenbergBlock blockName="" originalAttributes="e30="><![CDATA[]]><contentChunk><![CDATA[ ]]></contentChunk></gutenbergBlock><gutenbergBlock blockName="core/paragraph" originalAttributes="e30="><![CDATA[]]><contentChunk><![CDATA[
some par 2

]]></contentChunk></gutenbergBlock><![CDATA[]]></string>',
            ],
        ];
    }

    /**
     * @dataProvider processTranslationDataProvider
     */
    public function testProcessTranslation(string $inXML, string $expectedXML)
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

        $helper->setFieldsFilter($this->getFieldsFilterHelper());

        $result = $helper->processTranslation($params);

        $xml = $dom->saveXML($result->getNode());

        self::assertEquals($expectedXML, $xml);
    }

    /**
     * @return AcfDynamicSupport|MockObject
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

    private function getHelper($rulesManager = null, $replacerFactory = null): GutenbergBlockHelper
    {
        if ($rulesManager === null) {
            $rulesManager = $this->createMock(MediaAttachmentRulesManager::class);
        }
        if ($replacerFactory === null) {
            $replacerFactory = $this->createMock(ReplacerFactory::class);
        }
        $x = new GutenbergBlockHelper(
            $this->createMock(AcfDynamicSupport::class),
            $this->createMock(ContentSerializationHelper::class),
            $rulesManager,
            $replacerFactory,
            new SerializerJsonWithFallback(),
            $this->createMock(SettingsManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        );
        $x->setFieldsFilter($this->getFieldsFilterHelper());

        return $x;
    }

    private function getFieldsFilterHelper(): FieldsFilterHelper
    {
        return new FieldsFilterHelper(
            $this->getAcfDynamicSupportMock(),
            $this->createMock(ContentSerializationHelper::class),
            $this->getSettingsManagerMock(),
            $this->createMock(WordpressFunctionProxyHelper::class),
        );
    }
}
}
