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
            'rule-id-1' => new JsonFieldRule('_elementor_data', '$.title', 'translate'),
        ]);

        $wpProxy = $this->createWpProxy();
        $wpProxy->expects($this->once())
            ->method('wp_send_json_success')
            ->with($this->callback(function ($payload): bool {
                return $payload['rules'][0] === [
                        'id' => 'rule-id-1',
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
        $this->assertSame('_elementor_data', $savedRule['metaKey']);
        $this->assertSame('translate', $savedRule['replacerId']);
        $this->assertNotEmpty($savedRule['id']);
        $this->assertCount(1, $rulesManager->listItems());
    }

    public function testAjaxSaveRuleRejectsMissingFields(): void
    {
        $_POST = ['metaKey' => '_elementor_data'];

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
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.title',
            'replacerId' => 'translate',
        ];

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

    public function testAjaxResolveTypeReturnsPostType(): void
    {
        $_POST = ['id' => '42'];

        $wpProxy = $this->createWpProxy();
        $wpProxy->method('get_post_type')->with(42)->willReturn('page');

        $payload = null;
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $p) use (&$payload) {
            $payload = $p;
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxResolveType();

        $this->assertSame(['type' => 'page'], $payload);
    }

    public function testAjaxResolveTypeRejectsMissingId(): void
    {
        $_POST = [];
        $wpProxy = $this->createWpProxy();

        $errorCalled = false;
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $status) use (&$errorCalled) {
            $errorCalled = true;
            $this->assertSame(400, $status);
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxResolveType();
        $this->assertTrue($errorCalled);
    }

    public function testAjaxResolveTypeReturns404WhenPostMissing(): void
    {
        $_POST = ['id' => '999999'];
        $wpProxy = $this->createWpProxy();
        $wpProxy->method('get_post_type')->willReturn(false);

        $errorCalled = false;
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $status) use (&$errorCalled) {
            $errorCalled = true;
            $this->assertSame(404, $status);
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxResolveType();
        $this->assertTrue($errorCalled);
    }

    public function testAjaxDeleteRuleRemovesItem(): void
    {
        $manager = new JsonFieldRulesManager();
        $id = $manager->add([
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

    public function testAjaxListRulesReturns403WhenCapabilityMissing(): void
    {
        $wpProxy = $this->createWpProxy(false);

        $errorCalled = false;
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $status) use (&$errorCalled) {
            $errorCalled = true;
            $this->assertSame(403, $status);
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxListRules();
        $this->assertTrue($errorCalled);
    }

    public function testAjaxSaveRuleReturns403WhenCapabilityMissing(): void
    {
        $wpProxy = $this->createWpProxy(false);

        $errorCalled = false;
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $status) use (&$errorCalled) {
            $errorCalled = true;
            $this->assertSame(403, $status);
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxSaveRule();
        $this->assertTrue($errorCalled);
    }

    private function createWpProxy(bool $currentUserCan = true): WordpressFunctionProxyHelper|MockObject
    {
        $proxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $proxy->method('current_user_can')->willReturn($currentUserCan);
        return $proxy;
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
