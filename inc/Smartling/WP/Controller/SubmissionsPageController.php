<?php

namespace Smartling\WP\Controller;

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

/**
 * Class SubmissionsPageController
 * @package Smartling\WP\Controller
 */
class SubmissionsPageController extends WPAbstract implements WPHookInterface
{

    /**
     * @var Queue
     */
    private $queue;

    const LOGO_IMAGE = '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';

    /**
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param Queue $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    /**
     * @inheritdoc
     */
    public function register()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
    }

    public function menu()
    {
        add_menu_page(
            'Submissions Board',
            'Smartling',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP,
            'smartling-submissions-page',
            [$this, 'renderPage'],
            'data:image/png;base64,' . self::LOGO_IMAGE
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

    public function renderPage()
    {
        $table = new SubmissionTableWidget($this->getManager(), $this->getEntityHelper(), $this->getQueue());
        $table->prepare_items();
        $this->view($table);
    }
}