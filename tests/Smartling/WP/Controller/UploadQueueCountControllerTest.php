<?php

namespace Smartling\WP\Controller;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\AjaxSecurityChecker;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;

class UploadQueueCountControllerTest extends TestCase
{
    private UploadQueueManager $uploadQueueManager;
    private WordpressFunctionProxyHelper $wpProxy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadQueueManager = $this->createMock(UploadQueueManager::class);
        $this->wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
    }

    public function testHandleGetCountDoesNotReturnCountWhenUnauthorized(): void
    {
        $ajaxSecurity = $this->createMock(AjaxSecurityChecker::class);
        $ajaxSecurity->method('enforce')
            ->with('smartling_connector_ajax', SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP, $this->anything())
            ->willReturn(false);

        $this->uploadQueueManager->expects($this->never())->method('count');
        $this->wpProxy->expects($this->never())->method('wp_send_json_success');

        (new UploadQueueCountController($this->uploadQueueManager, $this->wpProxy, $ajaxSecurity))->handleGetCount();
    }

    public function testHandleGetCountReturnsCurrentQueueCount(): void
    {
        $ajaxSecurity = $this->createMock(AjaxSecurityChecker::class);
        $ajaxSecurity->method('enforce')->willReturn(true);
        $this->uploadQueueManager->method('count')->willReturn(7);

        $successArgs = null;
        $this->wpProxy->method('wp_send_json_success')->willReturnCallback(
            function (array $data) use (&$successArgs) {
                $successArgs = $data;
            }
        );

        (new UploadQueueCountController($this->uploadQueueManager, $this->wpProxy, $ajaxSecurity))->handleGetCount();

        $this->assertSame(['count' => 7], $successArgs);
    }
}
