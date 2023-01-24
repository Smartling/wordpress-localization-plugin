<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Tuner\FilterManager;
use Smartling\WP\WPHookInterface;

class FilterForm extends ControllerAbstract implements WPHookInterface
{
    public const SLUG = AdminPage::SLUG . '_filter_form';
    public const ACTION_SAVE = self::SLUG . '_save';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_' . self::SLUG, [$this, 'pageHandler']);
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'save']);
    }

    public function menu(): void
    {
        add_submenu_page(
            AdminPage::SLUG,
            'Custom Filter setup',
            'Custom Filter setup',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            self::SLUG,
            [
                $this,
                'pageHandler',
            ]
        );
    }

    public function pageHandler(): void
    {
        $this->setViewData(
            [
                'manager' => new FilterManager(),
            ]
        );
        $this->renderScript();
    }

    public function save(): void
    {
        $settings = &$_REQUEST['filter'];

        $data = [
            'pattern'       => stripslashes($settings['pattern']),
            'action'        => $settings['action'], //skip|copy|localize
            'serialization' => 'coma-separated',
            'value'         => 'reference',
            'type'          => $settings['type'], //post|media|taxonomy
        ];

        $id = $settings['id'];

        $manager = new FilterManager();
        $manager->loadData();

        if ('' === $id) {
            $manager->add($data);
        } else {
            $manager->updateItem($id, $data);
        }

        $manager->saveData();
        wp_redirect(admin_url('admin.php?page=' . AdminPage::SLUG));
    }
}
