<?php

namespace Smartling\WP\Controller;

use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\AjaxSecurityTrait;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\WP\WPHookInterface;

class UploadQueueCountController implements WPHookInterface
{
    use AjaxSecurityTrait;
    use LoggerSafeTrait;

    private const ACTION_NAME = 'smartling_upload_queue_count';

    public function __construct(
        private UploadQueueManager $uploadQueueManager,
        private WordpressFunctionProxyHelper $wpProxy,
    ) {
    }

    public function register(): void
    {
        $this->wpProxy->add_action('wp_ajax_' . self::ACTION_NAME, [$this, 'handleGetCount']);
    }

    public function handleGetCount(): void
    {
        if (!$this->enforceAjaxAuthorization(
            'smartling_connector_ajax',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            self::ACTION_NAME,
        )) {
            return;
        }

        $this->wpProxy->wp_send_json_success(['count' => $this->uploadQueueManager->count()]);
    }
}
