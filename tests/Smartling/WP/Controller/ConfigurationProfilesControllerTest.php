<?php

namespace Smartling\Tests\WP\Controller;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\WpObjectCache;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\ConfigurationProfilesController;

class ConfigurationProfilesControllerTest extends TestCase {

    private array $storedRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storedRequest = $_REQUEST;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_REQUEST = $this->storedRequest;
    }

    public function testPurgeUploadQueue(): void
    {
        $_REQUEST = ['_c_action' => ConfigurationProfilesController::ACTION_QUEUE_PURGE, 'argument' => QueueInterface::UPLOAD_QUEUE];

        $uploadQueueManager = $this->createMock(UploadQueueManager::class);
        $uploadQueueManager->expects($this->once())->method('purge');

        $x = new ConfigurationProfilesController(
            $this->createMock(ApiWrapperInterface::class),
            $this->createMock(LocalizationPluginProxyInterface::class),
            $this->createMock(PluginInfo::class),
            $this->createMock(SettingsManager::class),
            $this->createMock(SiteHelper::class),
            $this->createMock(SubmissionManager::class),
            $this->createMock(WpObjectCache::class),
            $this->createMock(QueueInterface::class),
            $uploadQueueManager,
        );

        $this->assertEquals([
            'data' => [],
            'errors' => [],
            'messages' => [],
            'status' => ['code' => 200, 'message' => 'Ok'],
        ], $x->processCnq());
    }
}
