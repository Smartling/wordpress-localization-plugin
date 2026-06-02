<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonFieldRulesManager;
use Smartling\WP\WPHookInterface;

class VisualConfiguratorPage extends ControllerAbstract implements WPHookInterface
{
    public const SLUG = 'smartling_visual_configurator';
    public const NONCE_ACTION = 'smartling_visual_configurator';
    public const ACTION_LIST_RULES = 'smartling_visual_configurator_list_rules';
    public const ACTION_SAVE_RULE = 'smartling_visual_configurator_save_rule';
    public const ACTION_DELETE_RULE = 'smartling_visual_configurator_delete_rule';
    public const ACTION_RESOLVE_TYPE = 'smartling_visual_configurator_resolve_type';

    public function __construct(
        private JsonFieldRulesManager $rulesManager,
        private ReplacerFactory $replacerFactory,
        private PluginInfo $pluginInfo,
        private WordpressFunctionProxyHelper $wpProxy,
    ) {
    }

    public function register(): void
    {
        $this->wpProxy->add_action('admin_menu', [$this, 'menu']);
        $this->wpProxy->add_action('network_admin_menu', [$this, 'menu']);
        $this->wpProxy->add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_LIST_RULES, [$this, 'ajaxListRules']);
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_SAVE_RULE, [$this, 'ajaxSaveRule']);
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_DELETE_RULE, [$this, 'ajaxDeleteRule']);
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_RESOLVE_TYPE, [$this, 'ajaxResolveType']);
    }

    public function menu(): void
    {
        add_submenu_page(
            AdminPage::SLUG,
            'Smartling Visual Configurator',
            'Visual Configurator',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            self::SLUG,
            [$this, 'pageHandler'],
        );
    }

    public function pageHandler(): void
    {
        $this->renderScript();
    }

    public function enqueue(string $hook): void
    {
        if (!str_contains($hook, self::SLUG)) {
            return;
        }

        $handle = $this->pluginInfo->getName() . 'visual-configurator';
        wp_enqueue_script(
            $handle,
            $this->pluginInfo->getUrl() . 'js/visual-configurator.js',
            ['wp-element', 'wp-components', 'wp-api-fetch', 'jquery'],
            $this->pluginInfo->getVersion(),
            true,
        );
        wp_localize_script($handle, 'smartlingVisualConfigurator', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restRoot' => rest_url('smartling-connector/v2'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'restNonce' => wp_create_nonce('wp_rest'),
            'replacerOptions' => $this->replacerFactory->getListForUi(),
            'actions' => [
                'list' => self::ACTION_LIST_RULES,
                'save' => self::ACTION_SAVE_RULE,
                'delete' => self::ACTION_DELETE_RULE,
                'resolveType' => self::ACTION_RESOLVE_TYPE,
            ],
        ]);
        wp_enqueue_style('wp-components');
    }

    public function ajaxListRules(): void
    {
        $this->verifyNonce();
        $this->rulesManager->loadData();
        $rules = [];
        foreach ($this->rulesManager->listItems() as $id => $rule) {
            $rules[] = ['id' => $id] + $rule->toArray();
        }
        $this->wpProxy->wp_send_json_success(['rules' => $rules]);
    }

    public function ajaxSaveRule(): void
    {
        $this->verifyNonce();
        try {
            $payload = $this->readRulePayload();
        } catch (\InvalidArgumentException $e) {
            $this->wpProxy->wp_send_json_error(['message' => $e->getMessage()], 400);
            return;
        }

        $data = (new JsonFieldRule(
            $payload['metaKey'],
            $payload['propertyPath'],
            $payload['replacerId'],
        ))->toArray();

        $id = isset($_POST['id']) && is_string($_POST['id'])
            ? $this->wpProxy->sanitize_text_field($this->wpProxy->wp_unslash($_POST['id']))
            : '';

        $this->rulesManager->loadData();
        if ($id === '') {
            $id = $this->rulesManager->add($data);
            if ($id === '') {
                $this->wpProxy->wp_send_json_error(['message' => 'Duplicate rule'], 409);
                return;
            }
        } else {
            if (!array_key_exists($id, $this->rulesManager->listItems())) {
                $this->wpProxy->wp_send_json_error(['message' => 'Rule not found'], 404);
                return;
            }
            $this->rulesManager->updateItem($id, $data);
        }
        $this->rulesManager->saveData();

        $this->wpProxy->wp_send_json_success(['rule' => ['id' => $id] + $data]);
    }

    public function ajaxResolveType(): void
    {
        $this->verifyNonce();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $this->wpProxy->wp_send_json_error(['message' => 'Missing or invalid id'], 400);
            return;
        }
        $type = $this->wpProxy->get_post_type($id);
        if ($type === false || $type === '' || $type === null) {
            $this->wpProxy->wp_send_json_error(['message' => "No post found with id $id"], 404);
            return;
        }
        $this->wpProxy->wp_send_json_success(['type' => $type]);
    }

    public function ajaxDeleteRule(): void
    {
        $this->verifyNonce();
        $id = isset($_POST['id']) && is_string($_POST['id'])
            ? $this->wpProxy->sanitize_text_field($this->wpProxy->wp_unslash($_POST['id']))
            : '';
        if ($id === '') {
            $this->wpProxy->wp_send_json_error(['message' => 'Missing id'], 400);
            return;
        }

        $this->rulesManager->loadData();
        $this->rulesManager->removeItem($id);
        $this->rulesManager->saveData();

        $this->wpProxy->wp_send_json_success(['id' => $id]);
    }

    private function verifyNonce(): void
    {
        $this->wpProxy->check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    /**
     * @return array{metaKey:string,propertyPath:string,replacerId:string}
     */
    private function readRulePayload(): array
    {
        $get = function (string $key): string {
            if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
                throw new \InvalidArgumentException("Missing field: $key");
            }
            return $this->wpProxy->sanitize_text_field($this->wpProxy->wp_unslash($_POST[$key]));
        };
        $payload = [
            'metaKey' => $get('metaKey'),
            'propertyPath' => $get('propertyPath'),
            'replacerId' => $get('replacerId'),
        ];
        foreach ($payload as $k => $v) {
            if ($v === '') {
                throw new \InvalidArgumentException("Field cannot be empty: $k");
            }
        }
        if (strlen($payload['propertyPath']) > 512) {
            throw new \InvalidArgumentException('propertyPath exceeds maximum length of 512 characters');
        }
        return $payload;
    }
}
