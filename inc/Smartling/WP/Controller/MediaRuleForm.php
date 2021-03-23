<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Tuner\FilterManager;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\WP\WPHookInterface;

class MediaRuleForm extends ControllerAbstract implements WPHookInterface
{
    public function register()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_customization_tuning_media_form', [$this, 'pageHandler']);
        add_action('admin_post_smartling_customization_tuning_media_form_save', [$this, 'save']);
    }

    public function menu()
    {
        add_submenu_page(
            'smartling_customization_tuning',
            'Custom Media Rule setup',
            'Custom Media Rule setup',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            'smartling_customization_tuning_media_form',
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
                'manager' => new MediaAttachmentRulesManager(),
            ]
        );
        $this->renderScript();
    }

    public function save()
    {
        $settings = $_REQUEST['media'];

        $data = [
            'block' => stripslashes($settings['block']),
            'path' => stripslashes($settings['path']),
        ];

        $id = $settings['id'];

        $manager = new MediaAttachmentRulesManager();
        $manager->loadData();

        if ('' === $id) {
            $manager->add($data);
        } else {
            $manager->updateItem($id, $data);
        }

        $manager->saveData();
        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=smartling_customization_tuning');
    }
}
