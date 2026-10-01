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
        $manager->saveData(); // persist so loadData() inside ajaxDeleteRule() reloads this exact item
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

    /**
     * The storage mock does not persist, so keep state across the controller's loadData() call
     */
    private function inMemoryManager(): JsonFieldRulesManager
    {
        return new class extends JsonFieldRulesManager {
            public function loadData(): void
            {
            }

            public function saveData(): void
            {
            }
        };
    }

    private function passthroughProxy(): WordpressFunctionProxyHelper|MockObject
    {
        $wpProxy = $this->createWpProxy();
        $wpProxy->method('sanitize_text_field')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->method('wp_unslash')->willReturnCallback(fn(string $v): string => $v);
        return $wpProxy;
    }

    public function testAjaxSaveRuleStoresExtendedRule(): void
    {
        $_POST = [
            'metaKey' => '_elementor_data',
            'propertyPath' => '$..title.text',
            'replacerId' => 'translate',
            'widgetType' => 'sovos-overview-hero',
            'conditions' => json_encode([['ancestor' => 0, 'key' => 'pattern_type', 'value' => 'x']]),
        ];
        $saved = null;
        $wpProxy = $this->passthroughProxy();
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $p) use (&$saved) {
            $saved = $p['rule'];
        });
        $manager = new JsonFieldRulesManager();

        $this->makeController($manager, $wpProxy)->ajaxSaveRule();

        $this->assertSame('sovos-overview-hero', $saved['widgetType']);
        $this->assertSame('x', $saved['conditions'][0]['value']);
        $this->assertCount(1, $manager->listItems());
    }

    /**
     * @dataProvider invalidSaveProvider
     */
    public function testAjaxSaveRuleRejectsInvalidExtendedInput(array $extra, string $path = '$..title.text', string $replacer = 'translate'): void
    {
        $_POST = ['metaKey' => 'm', 'propertyPath' => $path, 'replacerId' => $replacer] + $extra;
        $status = null;
        $wpProxy = $this->passthroughProxy();
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $s = null) use (&$status) {
            $status = $s;
        });
        $manager = new JsonFieldRulesManager();

        $this->makeController($manager, $wpProxy)->ajaxSaveRule();

        $this->assertSame(400, $status);
        $this->assertCount(0, $manager->listItems());
    }

    public static function invalidSaveProvider(): array
    {
        return [
            'bad widget type' => [['widgetType' => 'bad widget!']],
            'conditions not json' => [['conditions' => 'nope']],
            'positional path with widget' => [['widgetType' => 'w'], '$.a[*].b'],
            'unknown replacer' => [[], '$.a', 'bogus'],
        ];
    }

    public function testAjaxSaveRuleStoresAnywhereRuleWithoutWidgetOrConditions(): void
    {
        $_POST = [
            'metaKey' => '_elementor_data',
            'propertyPath' => '$..items.title',
            'replacerId' => 'translate',
            'matchMode' => 'anywhere',
            'widgetType' => '',
            'conditions' => '[]',
        ];
        $saved = null;
        $wpProxy = $this->passthroughProxy();
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $p) use (&$saved) {
            $saved = $p['rule'];
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxSaveRule();

        $this->assertSame('anywhere', $saved['matchMode']);
    }

    public function testAjaxImportAppliesSameValidationAsSave(): void
    {
        $manager = $this->inMemoryManager();
        $_POST = ['payload' => json_encode(['version' => 1, 'rules' => [
            ['metaKey' => '', 'propertyPath' => '$.a', 'replacerId' => 'copy'],
            ['metaKey' => 'm', 'propertyPath' => '$.' . str_repeat('a', 600), 'replacerId' => 'copy'],
            ['metaKey' => "m\n", 'propertyPath' => '$.a', 'replacerId' => 'copy'],
            ['metaKey' => 'm', 'propertyPath' => '$.ok', 'replacerId' => 'copy'],
        ]])];
        $result = null;
        $sanitized = [];
        $wpProxy = $this->createWpProxy();
        $wpProxy->method('wp_unslash')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->method('sanitize_text_field')->willReturnCallback(function (string $v) use (&$sanitized): string {
            $sanitized[] = $v;
            return $v;
        });
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $p) use (&$result) {
            $result = $p;
        });

        $this->makeController($manager, $wpProxy)->ajaxImport();

        $this->assertSame(1, $result['added']);
        $this->assertSame([1, 2, 3], array_column($result['invalid'], 'index'));
        $this->assertContains('$.ok', $sanitized, 'imported text fields must be sanitized like UI input');
    }

    public function testAjaxExportReturnsRulesWithoutIds(): void
    {
        $manager = $this->inMemoryManager();
        $manager->add(['metaKey' => 'm', 'propertyPath' => '$..a', 'replacerId' => 'translate', 'widgetType' => 'w']);
        $export = null;
        $wpProxy = $this->passthroughProxy();
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $p) use (&$export) {
            $export = $p['export'];
        });

        $this->makeController($manager, $wpProxy)->ajaxExport();

        $this->assertSame(JsonFieldRulesManager::EXPORT_FORMAT_VERSION, $export['version']);
        $this->assertSame([['metaKey' => 'm', 'propertyPath' => '$..a', 'replacerId' => 'translate', 'matchMode' => 'anywhere', 'widgetType' => 'w']], $export['rules']);
    }

    public function testAjaxImportAddsNewRulesKeepsExistingAndReportsInvalid(): void
    {
        $manager = $this->inMemoryManager();
        $existingId = $manager->add(['metaKey' => 'm', 'propertyPath' => '$.keep', 'replacerId' => 'copy']);
        $_POST = ['payload' => json_encode(['version' => 1, 'rules' => [
            ['metaKey' => 'm', 'propertyPath' => '$.keep', 'replacerId' => 'copy'],
            ['metaKey' => 'm', 'propertyPath' => '$..new', 'replacerId' => 'translate', 'widgetType' => 'w'],
            ['metaKey' => 'm', 'propertyPath' => '$..x', 'replacerId' => 'bogus'],
            ['metaKey' => 'm'],
            'garbage',
        ]])];
        $result = null;
        $wpProxy = $this->passthroughProxy();
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $p) use (&$result) {
            $result = $p;
        });

        $this->makeController($manager, $wpProxy)->ajaxImport();

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame([3, 4, 5], array_column($result['invalid'], 'index'));
        $items = $manager->listItems();
        $this->assertCount(2, $items);
        $this->assertSame('$.keep', $items[$existingId]->getPropertyPath());
    }

    /**
     * @dataProvider invalidImportProvider
     */
    public function testAjaxImportRejectsBadFiles(string $payload): void
    {
        $_POST = ['payload' => $payload];
        $status = null;
        $wpProxy = $this->passthroughProxy();
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $s = null) use (&$status) {
            $status = $s;
        });
        $manager = $this->inMemoryManager();
        $manager->add(['metaKey' => 'm', 'propertyPath' => '$.keep', 'replacerId' => 'copy']);

        $this->makeController($manager, $wpProxy)->ajaxImport();

        $this->assertSame(400, $status);
        $this->assertCount(1, $manager->listItems());
    }

    public static function invalidImportProvider(): array
    {
        return [
            'empty' => [''],
            'not json' => ['{{'],
            'no rules key' => [json_encode(['version' => 1])],
            'newer version' => [json_encode(['version' => 99, 'rules' => []])],
            'too large' => [str_repeat('a', 1048577)],
        ];
    }

    public function testAjaxPreviewReturnsCountAndValues(): void
    {
        $_POST = [
            'id' => '7',
            'metaKey' => '_elementor_data',
            'propertyPath' => '$..title.text',
            'replacerId' => 'translate',
            'widgetType' => 'sovos-overview-hero',
        ];
        $result = null;
        $wpProxy = $this->passthroughProxy();
        $wpProxy->method('getPostMeta')->willReturn(json_encode(\Smartling\Tests\Smartling\Tuner\JsonLeafMatcherTest::sovosData()));
        $wpProxy->method('wp_send_json_success')->willReturnCallback(function (array $p) use (&$result) {
            $result = $p;
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxPreview();

        $this->assertSame(['count' => 1, 'values' => ['Sovi AI']], $result);
    }

    public function testAjaxPreviewDeniedForPostUserCannotEdit(): void
    {
        $_POST = ['id' => '7', 'metaKey' => '_elementor_data', 'propertyPath' => '$..title.text', 'replacerId' => 'translate'];
        $status = null;
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('current_user_can')->willReturnCallback(fn(string $cap): bool => $cap !== 'edit_post');
        $wpProxy->method('sanitize_text_field')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->method('wp_unslash')->willReturnCallback(fn(string $v): string => $v);
        $wpProxy->expects($this->never())->method('getPostMeta');
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $s = null) use (&$status) {
            $status = $s;
        });

        $this->makeController(new JsonFieldRulesManager(), $wpProxy)->ajaxPreview();

        $this->assertSame(403, $status);
    }

    public function testImportAndExportRequireCapability(): void
    {
        $errors = 0;
        $wpProxy = $this->createWpProxy(false);
        $wpProxy->method('wp_send_json_error')->willReturnCallback(function ($p, $s = null) use (&$errors) {
            $this->assertSame(403, $s);
            $errors++;
        });
        $controller = $this->makeController(new JsonFieldRulesManager(), $wpProxy);
        $controller->ajaxImport();
        $controller->ajaxExport();
        $controller->ajaxPreview();
        $this->assertSame(3, $errors);
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
