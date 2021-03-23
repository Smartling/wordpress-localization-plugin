<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Tuner\ShortcodeManager;
use Smartling\WP\WPHookInterface;

class ShortcodeForm extends ControllerAbstract implements WPHookInterface
{
    public function register()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_customization_tuning_shortcode_form', [$this, 'pageHandler']);
        add_action('admin_post_smartling_customization_tuning_shortcode_form_save', [$this, 'save']);
    }

    public function menu()
    {
        add_submenu_page(
            'smartling_customization_tuning',
            'Custom shortcode setup',
            'Custom shortcode setup',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            'smartling_customization_tuning_shortcode_form',
            [
                $this,
                'pageHandler',
            ]
        );
    }

    public function pageHandler()
    {
        $this->setViewData(
            [
                'manager' => new ShortcodeManager(),
            ]
        );
        $this->renderScript();
    }

    public function save()
    {
        $settings = &$_REQUEST['shortcode'];
        $id = $settings['id'];
        $name = stripslashes($settings['name']);
        $manager = new ShortcodeManager();
        $manager->loadData();
        $data = [
            'name' => $name,
        ];
        if ('' === $id) {
            $manager->add($data);
        } else {
            $manager->updateItem($id, $data);
        }
        $manager->saveData();
        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=smartling_customization_tuning');
    }
}
