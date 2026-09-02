<?php

namespace Smartling\WP\Controller;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\WordpressFunctionProxyHelper;

class UploadQueueCountControllerTest extends TestCase
{
    private UploadQueueCountController $controller;
    private UploadQueueManager $uploadQueueManager;
    private WordpressFunctionProxyHelper $wpProxy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadQueueManager = $this->createMock(UploadQueueManager::class);
        $this->wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);

        $this->controller = new UploadQueueCountController(
            $this->uploadQueueManager,
            $this->wpProxy,
        );
    }

    public function testHandleGetCountReturns403WhenNonceInvalid(): void
    {
        $this->wpProxy->method('check_ajax_referer')->willReturn(false);

        $errorArgs = null;
        $this->wpProxy->method('wp_send_json_error')->willReturnCallback(
            function (array $data, int $status) use (&$errorArgs) {
                $errorArgs = ['data' => $data, 'status' => $status];
            }
        );
        $this->uploadQueueManager->expects($this->never())->method('count');

        $this->controller->handleGetCount();

        $this->assertNotNull($errorArgs);
        $this->assertSame(403, $errorArgs['status']);
    }

    public function testHandleGetCountReturns403WhenCapabilityMissing(): void
    {
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(false);

        $errorArgs = null;
        $this->wpProxy->method('wp_send_json_error')->willReturnCallback(
            function (array $data, int $status) use (&$errorArgs) {
                $errorArgs = ['data' => $data, 'status' => $status];
            }
        );
        $this->uploadQueueManager->expects($this->never())->method('count');

        $this->controller->handleGetCount();

        $this->assertNotNull($errorArgs);
        $this->assertSame(403, $errorArgs['status']);
    }

    public function testHandleGetCountReturnsCurrentQueueCount(): void
    {
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);
        $this->uploadQueueManager->method('count')->willReturn(7);

        $successArgs = null;
        $this->wpProxy->method('wp_send_json_success')->willReturnCallback(
            function (array $data) use (&$successArgs) {
                $successArgs = $data;
            }
        );

        $this->controller->handleGetCount();

        $this->assertSame(['count' => 7], $successArgs);
    }
}
