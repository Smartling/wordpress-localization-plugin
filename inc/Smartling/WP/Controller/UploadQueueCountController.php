<?php

namespace Smartling\WP\Controller;

use Smartling\DbAl\UploadQueueManager;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\WP\WPHookInterface;

class UploadQueueCountController implements WPHookInterface
{
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
        if ($this->wpProxy->check_ajax_referer('smartling_connector_ajax', '_wpnonce', false) === false) {
            $this->getLogger()->warning('Invalid nonce for action "' . self::ACTION_NAME . '"');
            $this->wpProxy->wp_send_json_error(['message' => 'Invalid nonce'], 403);
            return;
        }

        if (!$this->wpProxy->current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            $this->getLogger()->warning('User lacks capability "' . SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP . '" for action "' . self::ACTION_NAME . '"');
            $this->wpProxy->wp_send_json_error(['message' => 'Insufficient permissions'], 403);
            return;
        }

        $this->wpProxy->wp_send_json_success(['count' => $this->uploadQueueManager->count()]);
    }
}
