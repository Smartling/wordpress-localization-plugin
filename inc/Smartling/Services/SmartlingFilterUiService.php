<?php

namespace Smartling\Services;

use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Tuner\FilterManager;
use Smartling\Tuner\JsonFieldRulesManager;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\Tuner\ShortcodeManager;
use Smartling\WP\Controller\AdminPage;
use Smartling\WP\Controller\FilterForm;
use Smartling\WP\Controller\MediaRuleForm;
use Smartling\WP\Controller\ShortcodeForm;
use Smartling\WP\Controller\VisualConfiguratorPage;
use Smartling\WP\WPHookInterface;

class SmartlingFilterUiService implements WPHookInterface
{
    public function __construct(
        private MediaAttachmentRulesManager $mediaAttachmentRulesManager,
        private ReplacerFactory $replacerFactory,
        private JsonFieldRulesManager $jsonFieldRulesManager,
        private PluginInfo $pluginInfo,
        private WordpressFunctionProxyHelper $wpProxy,
    ) {
    }

    public function register(): void
    {
        if (1 === (int)GlobalSettingsManager::getFilterUiVisible()) {
            (new ShortcodeManager())->register();
            (new FilterManager())->register();

            // Enabling page and forms
            add_action('smartling_register_service', function () {
                (new AdminPage($this->mediaAttachmentRulesManager, $this->replacerFactory))->register();
                (new ShortcodeForm())->register();
                (new FilterForm())->register();
                (new MediaRuleForm($this->mediaAttachmentRulesManager, $this->replacerFactory))->register();
                (new VisualConfiguratorPage(
                    $this->jsonFieldRulesManager,
                    $this->replacerFactory,
                    $this->pluginInfo,
                    $this->wpProxy,
                ))->register();
            });
        }
    }
}
