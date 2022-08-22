<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\Cache;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Queue\Queue;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Table\SubmissionTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class SubmissionsPageController extends WPAbstract implements WPHookInterface
{
    private ApiWrapperInterface $apiWrapper;
    private Queue $queue;

    public function __construct(ApiWrapperInterface $apiWrapper, LocalizationPluginProxyInterface $connector, PluginInfo $pluginInfo, EntityHelper $entityHelper, SubmissionManager $manager, Cache $cache, Queue $queue)
    {
        parent::__construct($connector, $pluginInfo, $entityHelper, $manager, $cache);
        $this->apiWrapper = $apiWrapper;
        $this->queue = $queue;
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
        $table = new SubmissionTableWidget($this->apiWrapper, $this->getManager(), $this->getEntityHelper(), $this->queue);
        $table->prepare_items();
        $this->view($table);
    }
}
