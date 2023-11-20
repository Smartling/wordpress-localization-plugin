<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Base\SmartlingCore;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressLinkHelper;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Traits\InvokeMethodTrait;

class AbsoluteLinkedAttachmentCoreHelperTest extends TestCase
{
    use InvokeMethodTrait;

    private $wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->wpdb;
    }

    public function testLookForDirectGuidEntryQuery()
    {
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        $url = "https://example.com";
        global $wpdb;
        $wpdb = $this->getMockBuilder(\stdClass::class)->addMethods(['get_results', 'tables'])->getMock();
        $wpdb->base_prefix = 'wp_';
        /** @noinspection MockingMethodsCorrectnessInspection added with addMethods */
        $wpdb->method('tables')->willReturn([]);
        /** @noinspection MockingMethodsCorrectnessInspection added with addMethods */
        $wpdb->expects($this->once())->method('get_results')->with("SELECT `id` FROM `wp_posts` WHERE ( `guid` LIKE '%$url' )");

        $core = $this->createMock(SmartlingCore::class);
        $acfDynamicSupport = $this->createMock(AcfDynamicSupport::class);
        $x = new AbsoluteLinkedAttachmentCoreHelper(
            $core,
            $acfDynamicSupport,
            $this->createMock(SubmissionManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
            $this->createMock(WordpressLinkHelper::class),
        );
        $this->invokeMethod($x, 'lookForDirectGuidEntry', [$url]);
    }
}
