<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Tuner\ShortcodeManager;
use Smartling\WP\WPHookInterface;

class ShortcodeForm extends ControllerAbstract implements WPHookInterface
{
    public const SLUG = AdminPage::SLUG . '_shortcode_form';
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
            'Custom shortcode setup',
            'Custom shortcode setup',
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
        $this->viewData = [
            'manager' => new ShortcodeManager(),
        ];
        $this->renderScript();
    }

    public function save(): void
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
        wp_redirect(admin_url('admin.php?page=' . AdminPage::SLUG));
    }
}
