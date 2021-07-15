<?php

namespace Smartling\Tests\MultilingualPressConnector;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\MultilingualPressConnector;
use Smartling\Helpers\SiteHelper;

class MultilingualPressConnectorTest extends TestCase
{
    public function setUp(): void
    {
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        defined('OBJECT') || define('OBJECT', 'OBJECT');
    }

    public function testGetBlogNameByLocale()
    {
        $expected = "SELECT `english_name` FROM `wp_mlp_languages` WHERE ( `wp_locale` = 'test' ) LIMIT 0,1";
        $db = $this->getMockBuilder(\stdClass::class)->addMethods(['get_results'])->getMock();
        $db->base_prefix = 'wp_';
        /** @noinspection MockingMethodsCorrectnessInspection added with addMethods */
        $db->expects($this->once())->method('get_results')->with($expected)->willReturn([]);
        $x = new MultilingualPressConnector($this->createStub(SiteHelper::class), [], $db);
        $x->getBlogNameByLocale('test');
    }
}
