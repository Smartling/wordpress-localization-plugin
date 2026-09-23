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

    /**
     * Regression WP-1019: TranslationLockTableWidget::display() (inherited from
     * WP_List_Table) renders its own hidden `_wpnonce` field for its bulk
     * actions. If our own nonce field used that same name, the popup form
     * would submit two identically-named `_wpnonce` inputs; PHP keeps only the
     * last one in $_POST, which is the list table's value, so
     * verifyLockActionNonce() would reject every save with a valid-looking but
     * wrong nonce. See TranslationLock.php and TranslationLockTableWidget.php.
     *
     * WP_List_Table::display_tablenav() always names its bulk-action nonce
     * field '_wpnonce' (wp_nonce_field()'s own default, not something a
     * subclass configures), so that literal is the complete collision surface
     * - not an approximation of one. This unit test is intentionally a cheap,
     * fast canary; this suite has no WP core loaded (bootstrap_units.php only
     * requires the plugin's own autoloader, so WP_List_Table doesn't exist
     * here), so actually rendering the table to prove the two fields never
     * co-occur belongs in an E2E test - see
     * tests/playwright/translation-lock.spec.js, which drives the real popup
     * end to end and asserts the save behavior this bug broke.
     */
    public function testNonceFieldNameDoesNotCollideWithListTableBulkNonce(): void
    {
        $this->assertNotSame(
            '_wpnonce',
            TranslationLockController::LOCK_ACTION_NONCE_FIELD,
            'LOCK_ACTION_NONCE_FIELD must not be "_wpnonce" - WP_List_Table::display() ' .
            'already renders a hidden field with that name for its own bulk actions, ' .
            'and a duplicate would silently break every Translation Lock save.'
        );
    }
}
