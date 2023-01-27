<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\GutenbergReplacementRule;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\WP\WPHookInterface;

class MediaRuleForm extends ControllerAbstract implements WPHookInterface
{
    public const SLUG = AdminPage::SLUG . '_media_form';
    public const ACTION_SAVE = self::SLUG . '_save';

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
        add_action('admin_post_' . self::SLUG, [$this, 'pageHandler']);
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'save']);
    }

    public function menu(): void
    {
        add_submenu_page(
            AdminPage::SLUG,
            'Custom Media Rule setup',
            'Custom Media Rule setup',
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
        $this->renderScript();
    }

    public function save(): void
    {
        $settings = $_REQUEST['media'];

        $data = (new GutenbergReplacementRule(
            stripslashes($settings['block']),
            stripslashes($settings['path']),
            stripslashes($settings['replacerType']))
        )->toArray();

        $id = $settings['id'];

        $this->mediaAttachmentRulesManager->loadData();

        if ('' === $id) {
            $this->mediaAttachmentRulesManager->add($data);
        } else {
            $this->mediaAttachmentRulesManager->updateItem($id, $data);
        }

        $this->mediaAttachmentRulesManager->saveData();
        wp_redirect(admin_url('admin.php?page=' . AdminPage::SLUG));
    }
}
