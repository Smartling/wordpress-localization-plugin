<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Base\SmartlingCore;
use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\AbsoluteLinkedAttachmentCoreHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressLinkHelper;
use Smartling\Submissions\SubmissionEntity;
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

        $this->invokeMethod(new AbsoluteLinkedAttachmentCoreHelper(
            $this->createMock(SmartlingCore::class),
            $this->createMock(AcfDynamicSupport::class),
            $this->createMock(SubmissionManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
            $this->createMock(WordpressLinkHelper::class),
        ), 'lookForDirectGuidEntry', [$url]);
    }

    public function testProxyFunctionGetsCalledWhenLookingUpPossibleAttachmentId()
    {
        $core = $this->createMock(SmartlingCore::class);
        $core->method('getUploadFileInfo')->willReturn(['basedir' => __DIR__]);
        $core->method('getFullyRelateAttachmentPathByBlogId')->willReturn( basename(__FILE__));
        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $expectedPath = 'https://test.com/wp-content/uploads/2025/10/test.svg';
        $wordpressProxy->expects($this->once())->method('attachment_url_to_postid')
            ->with($expectedPath);
        $source = ['entity' => ['post_content' => <<<HTML
<!-- wp:core/image {"id":13} -->
<figure class="wp-block-image size-full"><img src="$expectedPath" alt="" class="wp-image-13"/></figure>
<!-- /wp:core/image -->
HTML]];
        (new AbsoluteLinkedAttachmentCoreHelper(
            $core,
            $this->createMock(AcfDynamicSupport::class),
            $this->createMock(SubmissionManager::class),
            $wordpressProxy,
            $this->createMock(WordpressLinkHelper::class),
        ))->processor(new AfterDeserializeContentEventParameters(
            $source,
            $this->createMock(SubmissionEntity::class),
            $this->createMock(Entity::class),
            [],
        ));
    }
}
