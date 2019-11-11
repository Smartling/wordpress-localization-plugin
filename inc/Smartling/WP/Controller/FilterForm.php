<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Tuner\FilterManager;
use Smartling\WP\WPHookInterface;

class FilterForm extends ControllerAbstract implements WPHookInterface
{
    public function register()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_customization_tuning_filter_form', [$this, 'pageHandler']);
        add_action('admin_post_smartling_customization_tuning_filter_form_save', [$this, 'save']);
    }

    public function menu()
    {
        add_submenu_page(
            'smartling_customization_tuning',
            'Custom Filter setup',
            'Custom Filter setup',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            'smartling_customization_tuning_filter_form',
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
                'manager' => new FilterManager(),
            ]
        );
        $this->renderScript();
    }

    public function save()
    {
        $settings = &$_REQUEST['filter'];

        $data = [
            'pattern'       => $settings['pattern'],
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
        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=smartling_customization_tuning');
    }
}