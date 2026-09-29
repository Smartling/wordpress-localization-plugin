<?php

namespace Smartling\WP\Controller;

use Smartling\ContentTypes\ExternalContentJsonRules;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonFieldRulesManager;
use Smartling\WP\WPHookInterface;

class VisualConfiguratorPage extends ControllerAbstract implements WPHookInterface
{
    use LoggerSafeTrait;

    public const SLUG = 'smartling_visual_configurator';
    public const NONCE_ACTION = 'smartling_visual_configurator';
    public const ACTION_LIST_RULES = 'smartling_visual_configurator_list_rules';
    public const ACTION_SAVE_RULE = 'smartling_visual_configurator_save_rule';
    public const ACTION_DELETE_RULE = 'smartling_visual_configurator_delete_rule';
    public const ACTION_RESOLVE_TYPE = 'smartling_visual_configurator_resolve_type';
    public const ACTION_PREVIEW = 'smartling_visual_configurator_preview';
    public const ACTION_EXPORT = 'smartling_visual_configurator_export';
    public const ACTION_IMPORT = 'smartling_visual_configurator_import';
    private const MAX_IMPORT_BYTES = 1048576;
    private const MAX_IMPORT_RULES = 500;
    private const PREVIEW_LIMIT = 15;
    private const PREVIEW_VALUE_LENGTH = 120;

    private ExternalContentJsonRules $evaluator;

    public function __construct(
        private JsonFieldRulesManager $rulesManager,
        private ReplacerFactory $replacerFactory,
        private PluginInfo $pluginInfo,
        private WordpressFunctionProxyHelper $wpProxy,
        ?ExternalContentJsonRules $evaluator = null,
    ) {
        $this->evaluator = $evaluator ?? new ExternalContentJsonRules($rulesManager, $replacerFactory, $wpProxy);
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
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_PREVIEW, [$this, 'ajaxPreview']);
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_EXPORT, [$this, 'ajaxExport']);
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_IMPORT, [$this, 'ajaxImport']);
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
                'preview' => self::ACTION_PREVIEW,
                'export' => self::ACTION_EXPORT,
                'import' => self::ACTION_IMPORT,
            ],
        ]);
        wp_enqueue_style('wp-components');
    }

    public function ajaxListRules(): void
    {
        if (!$this->verifyNonceAndCapabilities()) {
            return;
        }
        $this->rulesManager->loadData();
        $rules = [];
        foreach ($this->rulesManager->listItems() as $id => $rule) {
            $rules[] = ['id' => $id] + $rule->toArray();
        }
        $this->wpProxy->wp_send_json_success(['rules' => $rules]);
    }

    public function ajaxSaveRule(): void
    {
        if (!$this->verifyNonceAndCapabilities()) {
            return;
        }
        try {
            $data = $this->readRule()->toArray();
        } catch (\InvalidArgumentException $e) {
            $this->wpProxy->wp_send_json_error(['message' => $e->getMessage()], 400);
            return;
        }

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
        if (!$this->verifyNonceAndCapabilities()) {
            return;
        }
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
        if (!$this->verifyNonceAndCapabilities()) {
            return;
        }
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

    private function verifyNonceAndCapabilities(): bool
    {
        if ($this->wpProxy->check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false) === false) {
            $this->getLogger()->warning(sprintf('Invalid nonce for action "%s" from userId=%d', self::NONCE_ACTION, get_current_user_id()));
            $this->wpProxy->wp_send_json_error(['message' => 'Invalid nonce'], 403);
            return false;
        }
        if (!$this->wpProxy->current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP)) {
            $this->getLogger()->warning(sprintf('User %d lacks capability "%s"', get_current_user_id(), SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP));
            $this->wpProxy->wp_send_json_error(['message' => 'Insufficient permissions'], 403);
            return false;
        }
        return true;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function readRule(): JsonFieldRule
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
        $widgetType = isset($_POST['widgetType']) && is_string($_POST['widgetType'])
            ? $this->wpProxy->sanitize_text_field($this->wpProxy->wp_unslash($_POST['widgetType']))
            : '';
        $conditions = [];
        if (isset($_POST['conditions']) && is_string($_POST['conditions']) && $_POST['conditions'] !== '') {
            $decoded = json_decode($this->wpProxy->wp_unslash($_POST['conditions']), true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('conditions must be a JSON list');
            }
            $conditions = $decoded;
        }

        return $this->buildRule($payload + ['widgetType' => $widgetType, 'conditions' => $conditions]);
    }

    /**
     * Validates everything about a rule, including that the replacer exists
     *
     * @throws \InvalidArgumentException
     */
    private function buildRule(array $data): JsonFieldRule
    {
        $rule = JsonFieldRule::fromArray($data);
        try {
            $this->replacerFactory->getReplacer($rule->getReplacerId());
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Unknown rule type: {$rule->getReplacerId()}");
        }

        return $rule;
    }

    public function ajaxPreview(): void
    {
        if (!$this->verifyNonceAndCapabilities()) {
            return;
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $this->wpProxy->wp_send_json_error(['message' => 'Missing or invalid id'], 400);
            return;
        }
        try {
            $rule = $this->readRule();
        } catch (\InvalidArgumentException $e) {
            $this->wpProxy->wp_send_json_error(['message' => $e->getMessage()], 400);
            return;
        }
        $value = $this->wpProxy->getPostMeta($id, $rule->getMetaKey(), true);
        $json = is_string($value) ? json_decode($value, true) : null;
        if (!is_array($json)) {
            $this->wpProxy->wp_send_json_error(['message' => 'Meta field is missing or is not JSON'], 404);
            return;
        }
        $matches = $this->evaluator->getMatchedValues($json, $rule);
        $values = [];
        foreach (array_slice($matches, 0, self::PREVIEW_LIMIT) as $match) {
            $string = is_scalar($match) ? (string)$match : '';
            $values[] = mb_strlen($string) > self::PREVIEW_VALUE_LENGTH
                ? mb_substr($string, 0, self::PREVIEW_VALUE_LENGTH) . '…'
                : $string;
        }
        $this->wpProxy->wp_send_json_success(['count' => count($matches), 'values' => $values]);
    }

    public function ajaxExport(): void
    {
        if (!$this->verifyNonceAndCapabilities()) {
            return;
        }
        $this->rulesManager->loadData();
        $this->wpProxy->wp_send_json_success(['export' => $this->rulesManager->export()]);
    }

    public function ajaxImport(): void
    {
        if (!$this->verifyNonceAndCapabilities()) {
            return;
        }
        $raw = isset($_POST['payload']) && is_string($_POST['payload']) ? $this->wpProxy->wp_unslash($_POST['payload']) : '';
        if ($raw === '' || strlen($raw) > self::MAX_IMPORT_BYTES) {
            $this->wpProxy->wp_send_json_error(['message' => 'Import file is missing or too large'], 400);
            return;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !is_array($payload['rules'] ?? null)) {
            $this->wpProxy->wp_send_json_error(['message' => 'Not a valid rules export file'], 400);
            return;
        }
        if ((int)($payload['version'] ?? 0) > JsonFieldRulesManager::EXPORT_FORMAT_VERSION) {
            $this->wpProxy->wp_send_json_error(['message' => 'Export file was created by a newer version of the connector'], 400);
            return;
        }
        if (count($payload['rules']) > self::MAX_IMPORT_RULES) {
            $this->wpProxy->wp_send_json_error(['message' => 'Too many rules, maximum is ' . self::MAX_IMPORT_RULES], 400);
            return;
        }
        $rules = [];
        $invalid = [];
        foreach (array_values($payload['rules']) as $index => $data) {
            try {
                if (!is_array($data)) {
                    throw new \InvalidArgumentException('Rule must be an object');
                }
                $rules[] = $this->buildRule($data);
            } catch (\InvalidArgumentException $e) {
                $invalid[] = ['index' => $index + 1, 'message' => $e->getMessage()];
            }
        }
        $this->rulesManager->loadData();
        $result = $this->rulesManager->import($rules);
        $this->rulesManager->saveData();
        $this->getLogger()->info(sprintf('Imported JSON field rules: added=%d, skipped=%d, invalid=%d', $result['added'], $result['skipped'], count($invalid)));

        $this->wpProxy->wp_send_json_success($result + ['invalid' => $invalid]);
    }
}
