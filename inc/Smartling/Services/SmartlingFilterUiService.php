<?php

namespace Smartling\Services;

use Smartling\Tuner\FilterManager;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\Tuner\ShortcodeManager;
use Smartling\WP\Controller\AdminPage;
use Smartling\WP\Controller\FilterForm;
use Smartling\WP\Controller\MediaRuleForm;
use Smartling\WP\Controller\ShortcodeForm;
use Smartling\WP\WPHookInterface;

class SmartlingFilterUiService implements WPHookInterface
{
    private $mediaAttachmentRulesManager;
    public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager)
    {
        $this->mediaAttachmentRulesManager = $mediaAttachmentRulesManager;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     *
     * @return void
     */
    public function register()
    {
        if (1 === (int)GlobalSettingsManager::getFilterUiVisible()) {
            (new ShortcodeManager())->register();
            (new FilterManager())->register();

            // Enabling page and forms
            add_action('smartling_register_service', function () {
                (new AdminPage($this->mediaAttachmentRulesManager))->register();
                (new ShortcodeForm())->register();
                (new FilterForm())->register();
                (new MediaRuleForm())->register();
            });
        }
    }
}
