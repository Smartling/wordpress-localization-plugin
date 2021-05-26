<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\WP\WPHookInterface;

class MediaRuleForm extends ControllerAbstract implements WPHookInterface
{
    public const ACTION_SAVE = 'smartling_customization_tuning_media_form_save';

    protected MediaAttachmentRulesManager $mediaAttachmentRulesManager;
    protected ReplacerFactory $replacerFactory;

    public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager, ReplacerFactory $replacerFactory)
    {
        $this->mediaAttachmentRulesManager = $mediaAttachmentRulesManager;
        $this->replacerFactory = $replacerFactory;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_customization_tuning_media_form', [$this, 'pageHandler']);
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'save']);
    }

    public function menu(): void
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

    public function pageHandler(): void
    {
        $this->renderScript();
    }

    public function save(): void
    {
        $settings = $_REQUEST['media'];

        $data = [
            'block' => stripslashes($settings['block']),
            'path' => stripslashes($settings['path']),
            'replacerId' => stripslashes($settings['replacerType']),
        ];

        $id = $settings['id'];

        $this->mediaAttachmentRulesManager->loadData();

        if ('' === $id) {
            $this->mediaAttachmentRulesManager->add($data);
        } else {
            $this->mediaAttachmentRulesManager->updateItem($id, $data);
        }

        $this->mediaAttachmentRulesManager->saveData();
        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=smartling_customization_tuning');
    }
}
