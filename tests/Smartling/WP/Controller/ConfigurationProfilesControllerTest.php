<?php

namespace Smartling\Tests\WP\Controller;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\WpObjectCache;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Queue\QueueInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
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
        $_REQUEST = ['_c_action' => ConfigurationProfilesController::ACTION_QUEUE_PURGE, 'argument' => QueueInterface::VIRTUAL_UPLOAD_QUEUE];

        $submission = $this->createMock(SubmissionEntity::class);

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('find')->willReturn([$submission]);

        $x = new ConfigurationProfilesController(
            $this->createMock(LocalizationPluginProxyInterface::class),
            $this->createMock(PluginInfo::class),
            $this->createMock(SettingsManager::class),
            $this->createMock(SiteHelper::class),
            $submissionManager,
            $this->createMock(WpObjectCache::class)
        );

        $submission->expects($this->once())->method('setStatus')->with(SubmissionEntity::SUBMISSION_STATUS_CANCELLED);
        $submissionManager->expects($this->once())->method('storeSubmissions')->with([$submission]);

        $this->assertEquals([
            'data' => [],
            'errors' => [],
            'messages' => [],
            'status' => ['code' => 200, 'message' => 'Ok'],
        ], $x->processCnq());
    }
}
