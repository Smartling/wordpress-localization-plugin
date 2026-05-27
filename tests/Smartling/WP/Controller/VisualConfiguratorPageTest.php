<?php

namespace Smartling\Tests\Smartling\WP\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonFieldRulesManager;
use Smartling\WP\Controller\VisualConfiguratorPage;

class VisualConfiguratorPageTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    public function testAjaxListRulesEmitsRulesViaProxy(): void
    {
        $rulesManager = $this->createMock(JsonFieldRulesManager::class);
        $rulesManager->expects($this->once())->method('loadData');
        $rulesManager->method('listItems')->willReturn([
            'rule-id-1' => new JsonFieldRule('page', '_elementor_data', '$.title', 'translate'),
        ]);

        $wpProxy = $this->createWpProxy();
        $wpProxy->expects($this->once())
            ->method('wp_send_json_success')
            ->with($this->callback(function ($payload): bool {
                return $payload['rules'][0] === [
                        'id' => 'rule-id-1',
                        'contentType' => 'page',
                        'metaKey' => '_elementor_data',
                        'propertyPath' => '$.title',
                        'replacerId' => 'translate',
                    ];
            }));

        $controller = $this->makeController($rulesManager, $wpProxy);
        $controller->ajaxListRules();
    }

    public function testAjaxSaveRuleStoresAndReturnsRule(): void
    {
        $_POST = [
            'contentType' => 'page',
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.title',
            'replacerId' => 'translate',
        ];

        $rulesManager = new JsonFieldRulesManager();
        $wpProxy = $this->createWpProxy();
        $wpProxy->method('sanitize_text_field')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->method('wp_unslash')->willReturnCallback(fn(string $v): string => $v);

        $savedRule = null;
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $payload) use (&$savedRule) {
            $savedRule = $payload['rule'];
        });

        $controller = $this->makeController($rulesManager, $wpProxy);
        $controller->ajaxSaveRule();

        $this->assertIsArray($savedRule);
        $this->assertSame('page', $savedRule['contentType']);
        $this->assertSame('translate', $savedRule['replacerId']);
        $this->assertNotEmpty($savedRule['id']);
        $this->assertCount(1, $rulesManager->listItems());
    }

    public function testAjaxSaveRuleRejectsMissingFields(): void
    {
        $_POST = ['contentType' => 'page'];

        $wpProxy = $this->createWpProxy();
        $wpProxy->method('sanitize_text_field')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->method('wp_unslash')->willReturnCallback(fn(string $v): string => $v);

        $errorCalled = false;
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($payload, $status = null) use (&$errorCalled) {
            $errorCalled = true;
            $this->assertSame(400, $status);
            $this->assertStringContainsString('Missing', $payload['message']);
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxSaveRule();

        $this->assertTrue($errorCalled);
    }

    public function testAjaxSaveRuleRejectsDuplicate(): void
    {
        $_POST = [
            'contentType' => 'page',
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.title',
            'replacerId' => 'translate',
        ];

        // The manager's add() returns '' for duplicates; mock that directly so we test the controller's response.
        $manager = $this->createMock(JsonFieldRulesManager::class);
        $manager->method('add')->willReturn('');

        $wpProxy = $this->createWpProxy();
        $wpProxy->method('sanitize_text_field')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->method('wp_unslash')->willReturnCallback(fn(string $v): string => $v);

        $errorCalled = false;
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($payload, $status = null) use (&$errorCalled) {
            $errorCalled = true;
            $this->assertSame(409, $status);
        });

        $this->makeController($manager, $wpProxy)->ajaxSaveRule();

        $this->assertTrue($errorCalled);
    }

    public function testAjaxDeleteRuleRemovesItem(): void
    {
        $manager = new JsonFieldRulesManager();
        $id = $manager->add([
            'contentType' => 'page',
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.title',
            'replacerId' => 'translate',
        ]);
        $_POST = ['id' => $id];

        $wpProxy = $this->createWpProxy();
        $wpProxy->method('sanitize_text_field')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->method('wp_unslash')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->expects($this->once())->method('wp_send_json_success');

        $this->makeController($manager, $wpProxy)->ajaxDeleteRule();

        $this->assertCount(0, $manager->listItems());
    }

    private function createWpProxy(): WordpressFunctionProxyHelper|MockObject
    {
        return $this->createMock(WordpressFunctionProxyHelper::class);
    }

    private function makeController(JsonFieldRulesManager $manager, WordpressFunctionProxyHelper $wpProxy): VisualConfiguratorPage
    {
        $pluginInfo = $this->createMock(PluginInfo::class);
        $submissionManager = $this->createMock(SubmissionManager::class);
        return new VisualConfiguratorPage(
            $manager,
            new ReplacerFactory($submissionManager),
            $pluginInfo,
            $wpProxy,
        );
    }
}
