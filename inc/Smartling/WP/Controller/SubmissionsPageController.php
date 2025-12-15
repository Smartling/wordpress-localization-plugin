<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Queue\Queue;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Table\SubmissionTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class SubmissionsPageController extends WPAbstract implements WPHookInterface
{
    public function __construct(
        protected ApiWrapperInterface $api,
        LocalizationPluginProxyInterface $connector,
        PluginInfo $pluginInfo,
        SettingsManager $settingsManager,
        SiteHelper $siteHelper,
        SubmissionManager $manager,
        Cache $cache,
        private Queue $queue,
    ) {
        parent::__construct($api, $connector, $pluginInfo, $settingsManager, $siteHelper, $manager, $cache);
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
    }

    public function menu(): void
    {
        add_menu_page(
            'Submissions Board',
            'Smartling',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP,
            'smartling-submissions-page',
            [$this, 'renderPage'],
            'none',
        );

        add_submenu_page(
            'smartling-submissions-page',
            'Translation Progress',
            'Translation Progress',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP,
            'smartling-submissions-page',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        $table = new SubmissionTableWidget($this->api, $this->localizationPluginProxy, $this->settingsManager, $this->siteHelper, $this->getManager(), $this->queue);
        $table->prepare_items();
        $this->view($table);
    }
}
