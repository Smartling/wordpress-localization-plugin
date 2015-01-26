<?php

namespace Smartling\WP\Controller;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\View\SubmissionTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class SubmissionsPageController
 * @package Smartling\WP\Controller
 */
class SubmissionsPageController
    extends WPAbstract
    implements WPHookInterface
{

    /**
     * @var SubmissionManager $submissionManager
     */
    private $submissionManager;

    /**
     * @param LoggerInterface $logger
     * @param LocalizationPluginProxyInterface $multiLingualConnector
     * @param PluginInfo $pluginInfo
     * @param SubmissionManager $manager
     */
    public function __construct(
        LoggerInterface $logger,
        LocalizationPluginProxyInterface $multiLingualConnector,
        PluginInfo $pluginInfo,
        SubmissionManager $manager
    )
    {
        parent::__construct($logger, $multiLingualConnector, $pluginInfo);

        $this->submissionManager = $manager;
    }

    /**
     * @inheritdoc
     */
    public function register()
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('network_admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        add_menu_page('Submissions', 'Smartling Connector', 'Administrator', 'smartling-submissions', array( $this, 'renderPage')  );
    }


    public function renderPage()
    {
        $table = new SubmissionTableWidget($this->submissionManager);

        $this->view($table);
    }
}