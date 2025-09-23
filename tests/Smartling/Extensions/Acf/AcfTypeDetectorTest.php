<?php

namespace Smartling\Tests\Smartling\Extensions\Acf;

use PHPUnit\Framework\TestCase;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Extensions\Acf\AcfTypeDetector;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\MediaBasedProcessor;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WpObjectCache;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class AcfTypeDetectorTest extends TestCase
{
    private $acfStores;
    protected function setUp(): void
    {
        global $acf_stores;
        $this->acfStores = $acf_stores;
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    protected function tearDown(): void
    {
        global $acf_stores;
        $acf_stores = $this->acfStores;
    }

    /**
     * @dataProvider providerGetProcessorByMetaFields
     */
    public function testGetProcessorByMetaFields(string $fieldName, array $metaFields = [])
    {
        $cache = $this->createMock(Cache::class);
        $cache->method('get')->willReturn(false);
        $x = $this->getMockBuilder(AcfTypeDetector::class)
            ->setConstructorArgs([
                new ContentHelper(
                    $this->createMock(ContentEntitiesIOFactory::class),
                    $this->createMock(SiteHelper::class),
                    new WordpressFunctionProxyHelper()
                ),
                $cache,
            ])
            ->onlyMethods(["getProcessorByFieldKey"])
            ->getMock();
        $x->expects($this->once())->method("getProcessorByFieldKey")->with("field_6835dc2b65da8", $fieldName);
        $x->getProcessorByMetaFields($fieldName, $metaFields);
    }

    private function providerGetProcessorByMetaFields()
    {
        return [
            [
                "meta/field/0",
                ["field/0" => "irrelevant", "_field" => "field_6835dc2b65da8"],
            ],
            [
                "meta/field",
                ["field" => "irrelevant", "_field" => "field_6835dc2b65da8"],
            ],
            [
                "field",
                ["field" => "irrelevant", "_field" => "field_6835dc2b65da8"],
            ],
        ];
    }

    public function testGetProcessorForGutenberg()
    {
        global $acf_stores;
        if (!class_exists('ACF_Data')) {
            $this->markTestSkipped('No ACF data found. This is ok when running tests with no ACF plugin or no wordpress loaded. The test will work when running as part of integration suite.');
        }
        $groups = $this->createPartialMock('ACF_Data', ['get_data']);
        $groups->method('get_data')->willReturn([]);
        $fields = $this->createPartialMock('ACF_Data', ['get_data']);
        $fields->method('get_data')->willReturn([
            'field_5eb1344b55a84' => [
                'global_type' => 'field',
                'type' => 'image',
                'name' => 'media',
                'key' => 'field_5eb1344b55a84',
                'parent' => '',
            ]
        ]);
        $acf_stores = [
            'local-groups' => $groups,
            'local-fields' => $fields,
        ];

        $settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
        $settingsManager->method('getActiveProfiles')->willReturn([]);
        $siteHelper = $this->createMock(SiteHelper::class);
        $siteHelper->method('listBlogs')->willReturn([]);

        $ads = new AcfDynamicSupport(
            new ArrayHelper(),
            $settingsManager,
            $siteHelper,
            $this->createMock(SubmissionManager::class),
            new WordpressFunctionProxyHelper(),
        );
        $ads->run();

        $fields = json_decode('{"entity\/post_content\/acf\/testimonial\/data\/media":"297",' .
            '"entity\/post_content\/acf\/testimonial\/data\/_media":"field_5eb1344b55a84"}', true);
        self::assertInstanceOf(
            MediaBasedProcessor::class,
            (new AcfTypeDetector(new ContentHelper($this->createMock(ContentEntitiesIOFactory::class), $siteHelper, new WordpressFunctionProxyHelper()), new WpObjectCache()))
                ->getProcessorForGutenberg(array_keys($fields)[0], $fields)
        );
    }
}
