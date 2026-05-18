<?php

namespace Smartling\Tests\Smartling\Extensions\Acf;

use PHPUnit\Framework\TestCase;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Extensions\Acf\AcfTypeDetector;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;

class AcfTypeDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
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
                $this->createMock(AcfDynamicSupport::class),
                $cache,
                new ContentHelper(
                    $this->createMock(ContentEntitiesIOFactory::class),
                    $this->createMock(SiteHelper::class),
                    new WordpressFunctionProxyHelper()
                ),
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

}
