<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\AdminNoticesHelper;
use Smartling\Helpers\GutenbergReplacementRule;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Tuner\FilterManager;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\Tuner\ShortcodeManager;
use Smartling\WP\Table\LocalizationRulesTableWidget;
use Smartling\WP\Table\MediaAttachmentTableWidget;
use Smartling\WP\Table\ShortcodeTableClass;
use Smartling\WP\WPHookInterface;

class AdminPage extends ControllerAbstract implements WPHookInterface
{
    public const SLUG = 'smartling_customization_tuning';
    public const ACTION_EXPORT_GUTENBERG_RULES = 'smartling_export_gutenberg_rules';
    public const ACTION_IMPORT_GUTENBERG_RULES = 'smartling_import_gutenberg_rules';
    private MediaAttachmentRulesManager $mediaAttachmentRulesManager;
    private ReplacerFactory $replacerFactory;

    public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager, ReplacerFactory $replacerFactory)
    {
        $this->mediaAttachmentRulesManager = $mediaAttachmentRulesManager;
        $this->replacerFactory = $replacerFactory;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_' . self::SLUG, [$this, 'pageHandler']);
        add_action('admin_post_' . self::ACTION_EXPORT_GUTENBERG_RULES, [$this, 'exportMediaAttachmentRules']);
        add_action('admin_post_' . self::ACTION_IMPORT_GUTENBERG_RULES, [$this, 'importMediaAttachmentRules']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Gutenberg blocks, Shortcodes and Field filters tuning',
            'Fine-Tune',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            self::SLUG,
            [
                $this,
                'pageHandler',
            ]
        );
    }

    public function exportMediaAttachmentRules(): void
    {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="smartling_media_attachment_rules.txt"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        foreach ($this->mediaAttachmentRulesManager->listItems() as $rule) {
            echo "$rule\n";
        }
    }

    public function importMediaAttachmentRules(): void
    {
        $added = 0;
        $duplicates = 0;
        $skipped = 0;
        $this->mediaAttachmentRulesManager->loadData();
        foreach (explode("\n", $this->getUploadedFileContents('file')) as $ruleString) {
            try {
                $rule = GutenbergReplacementRule::fromString($ruleString);
            } catch (\InvalidArgumentException) {
                if ($ruleString !== '') {
                    ++ $skipped;
                    AdminNoticesHelper::addWarning("Unable to create Gutenberg replacement rule from string " . esc_html($ruleString));
                }
                continue;
            }
            $id = $this->mediaAttachmentRulesManager->add($rule->toArray());
            if ($id === '') {
                ++$duplicates;
            } else {
                ++$added;
            }
        }
        $this->mediaAttachmentRulesManager->saveData();
        AdminNoticesHelper::addInfo("Done importing Gutenberg block rules, $added added, $duplicates duplicates $skipped skipped");
        wp_redirect(admin_url('admin.php?page=' . self::SLUG));
    }

    private function processAction(): void
    {
        $action = $_REQUEST['action'];
        $type = $_REQUEST['type'];

        if ('delete' !== strtolower($action)) {
            return;
        }

        switch (strtolower($type)) {
            case 'shortcode':
                $manager = new ShortcodeManager();
                break;
            case 'filter':
                $manager = new FilterManager();
                break;
            case 'media':
                $manager = $this->mediaAttachmentRulesManager;
                break;
            default:
                return;
        }

        if (array_key_exists('id', $_REQUEST)) {
            $id = $_REQUEST['id'];
            $manager->loadData();
            if (isset($manager[$id])) {
                unset($manager[$id]);
                $manager->saveData();
            }
        }
    }

    public function pageHandler(): void
    {
        if (array_key_exists('action', $_REQUEST) && array_key_exists('type', $_REQUEST)) {
            $this->processAction();
        }

        $this->setViewData(
            [
                'shortcodes' => new ShortcodeTableClass(new ShortcodeManager()),
                'filters' => new LocalizationRulesTableWidget(new FilterManager()),
                'media' => new MediaAttachmentTableWidget($this->mediaAttachmentRulesManager, $this->replacerFactory),
            ]
        );
        $this->renderScript();
    }
}
