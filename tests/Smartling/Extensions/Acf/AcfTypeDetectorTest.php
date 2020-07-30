<?php

namespace Smartling\Tests\Smartling\Extensions\Acf;

use PHPUnit\Framework\TestCase;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Extensions\Acf\AcfTypeDetector;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\MetaFieldProcessor\BulkProcessors\MediaBasedProcessor;
use Smartling\Helpers\SiteHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class AcfTypeDetectorTest extends TestCase
{
    protected function setUp()
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    public function testGetProcessorForGutenberg()
    {
        global $acf_stores;
        $groups = $this->getMockBuilder('ACF_Data')->setMethods(['get_data'])->getMock();
        $groups->method('get_data')->willReturn([]);
        $fields = $this->getMockBuilder('ACF_Data')->setMethods(['get_data'])->getMock();
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

        $entityHelper = $this->getMock(EntityHelper::class);
        $settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
        $settingsManager->method('getActiveProfiles')->willReturn([]);
        $siteHelper = $this->getMock(SiteHelper::class);
        $siteHelper->method('listBlogs')->willReturn([]);
        $entityHelper->method('getSiteHelper')->willReturn($siteHelper);
        $entityHelper->method('getSettingsManager')->willReturn($settingsManager);

        $ads = new AcfDynamicSupport($entityHelper);
        $ads->run();

        $fields = json_decode('{"entity\/post_content\/acf\/testimonial\/data\/media":"297",' .
            '"entity\/post_content\/acf\/testimonial\/data\/_media":"field_5eb1344b55a84"}', true);
        self::assertInstanceOf(
            MediaBasedProcessor::class,
            (new AcfTypeDetector(new ContentHelper(), new Cache()))
                ->getProcessorForGutenberg(array_keys($fields)[0], $fields)
        );
    }
}
