<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Tuner\FilterManager;
use Smartling\Tuner\ShortcodeManager;
use Smartling\WP\Table\LocalizationRulesTableWidget;
use Smartling\WP\Table\ShortcodeTableClass;
use Smartling\WP\WPHookInterface;

class AdminPage extends ControllerAbstract implements WPHookInterface
{

    public function register()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_customization_tuning', [$this, 'pageHandler']);
    }

    public function menu()
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Shortcodes and Field filters tuning',
            'Fine Tune',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            'smartling_customization_tuning',
            [
                $this,
                'pageHandler',
            ]
        );
    }

    private function processAction()
    {
        $action = &$_REQUEST['action'];
        $type = &$_REQUEST['type'];

        $manager = null;
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
            default:
                return;
        }

        if (array_key_exists('id', $_REQUEST)) {
            $id = &$_REQUEST['id'];
            $manager->loadData();
            if (isset($manager[$id])) {
                unset($manager[$id]);
                $manager->saveData();
            }
        }
    }

    public function pageHandler()
    {
        if (array_key_exists('action', $_REQUEST) && array_key_exists('type', $_REQUEST)) {
            $this->processAction();
        }

        $this->setViewData(
            [
                'shortcodes' => new ShortcodeTableClass(new ShortcodeManager()),
                'filters'    => new LocalizationRulesTableWidget(new FilterManager()),
            ]
        );
        $this->renderScript();
    }
}