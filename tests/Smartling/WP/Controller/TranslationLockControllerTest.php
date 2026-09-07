<?php

namespace Smartling\WP\Controller;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\NonceVerifier;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class TranslationLockControllerTest extends TestCase
{
    private SubmissionManager $submissionManager;
    private WordpressFunctionProxyHelper $wpProxy;
    private TranslationLockController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->submissionManager = $this->createMock(SubmissionManager::class);
        $this->wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);

        $this->controller = new TranslationLockController(
            $this->createMock(ApiWrapperInterface::class),
            $this->createMock(LocalizationPluginProxyInterface::class),
            $this->createMock(PluginInfo::class),
            $this->createMock(SettingsManager::class),
            $this->createMock(SiteHelper::class),
            $this->submissionManager,
            $this->createMock(Cache::class),
            $this->createMock(ContentHelper::class),
            $this->wpProxy,
            new NonceVerifier($this->wpProxy),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    public function testHandleFormPostAppliesChangesWithValidNonce(): void
    {
        $submissionId = 5;
        $_GET['submission'] = (string)$submissionId;
        $_POST = [
            TranslationLockController::LOCK_ACTION_NONCE_FIELD => 'valid-nonce',
            'lockField' => ['entity/post_title' => '1'],
            'lock_page' => '1',
        ];

        $this->wpProxy->method('wp_verify_nonce')
            ->with('valid-nonce', TranslationLockController::LOCK_ACTION_NONCE_ACTION)
            ->willReturn(1);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->expects($this->once())->method('setLockedFields')->with(['entity/post_title']);
        $submission->expects($this->once())->method('setIsLocked')->with(1);

        $this->submissionManager->method('findByIds')->with([$submissionId])->willReturn([$submission]);
        $this->submissionManager->expects($this->once())->method('storeEntity')->with($submission);

        $this->controller->handleFormPost();
    }

    /**
     * A missing or invalid nonce must reject the request before any submission is looked up
     * or modified.
     */
    public function testHandleFormPostRejectsInvalidNonce(): void
    {
        $_GET['submission'] = '5';
        $_POST = [TranslationLockController::LOCK_ACTION_NONCE_FIELD => 'not-a-valid-nonce'];

        $this->wpProxy->method('wp_verify_nonce')->willReturn(false);

        $this->submissionManager->expects($this->never())->method('findByIds');
        $this->submissionManager->expects($this->never())->method('storeEntity');

        $this->controller->handleFormPost();
    }

    public function testHandleFormPostRejectsMissingNonce(): void
    {
        $_GET['submission'] = '5';
        $_POST = [];

        $this->wpProxy->expects($this->never())->method('wp_verify_nonce');

        $this->submissionManager->expects($this->never())->method('findByIds');
        $this->submissionManager->expects($this->never())->method('storeEntity');

        $this->controller->handleFormPost();
    }
}
