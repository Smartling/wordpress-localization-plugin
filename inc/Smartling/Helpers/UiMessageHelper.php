<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;

class UiMessageHelper
{
    private const CACHE_KEY_PREFIX = 'smartling.ui.message.';
    public const DISMISS_MESSAGE_ACTION = 'smartling_dismiss_message';

    public static function dismissMessage(): void
    {
        if (check_ajax_referer(self::DISMISS_MESSAGE_ACTION, '_wpnonce', false) === false) {
            Bootstrap::getLogger()->warning(sprintf('Invalid nonce for action "%s" from userId=%d', self::DISMISS_MESSAGE_ACTION, get_current_user_id()));
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            return;
        }
        if (!current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP)) {
            Bootstrap::getLogger()->warning(sprintf('User %d lacks capability "%s"', get_current_user_id(), SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP));
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
            return;
        }
        $cache = self::getCache();
        $hash = isset($_POST['hash']) ? sanitize_text_field(wp_unslash($_POST['hash'])) : '';
        if ($hash !== '') {
            $cache->set(self::CACHE_KEY_PREFIX . $hash, true, 60 * 60 * 180);
        }
        wp_send_json_success();
    }

    public static function displayMessages(): void
    {
        $cache = self::getCache();
        $type = 'error';
        $messages = DiagnosticsHelper::getMessages();
        if (0 < count($messages)) {
            $msg = '';
            foreach ($messages as $message) {
                if (!$cache->get(self::getCacheKey($message))) {
                    $msg .= sprintf(
                        '<div class="%s"><h4 style="margin-bottom: 0; padding-bottom: 0">Smartling connector:</h4><p>%s</p></div>',
                        $type,
                        $message . '<br /><a href="" onclick="' . self::getClickHandler($message) . '" style="display: block; text-align: right">Dismiss</a>',
                    );
                }
            }
            echo $msg;
            DiagnosticsHelper::reset();
        }
    }

    private static function getCache(): Cache
    {
        return new WpTransientCache();
    }

    private static function getCacheHash(string $string): string
    {
        return md5($string);
    }

    private static function getCacheKey(string $string): string
    {
        return self::CACHE_KEY_PREFIX . self::getCacheHash($string);
    }

    private static function getClickHandler(string $string): string
    {
        $action = self::DISMISS_MESSAGE_ACTION;
        $hash = self::getCacheHash($string);
        $nonce = wp_create_nonce(self::DISMISS_MESSAGE_ACTION);
        return <<<JS
jQuery.post(ajaxurl + '?action=$action', {hash: '$hash', _wpnonce: '$nonce'});
this.parentNode.parentNode.style.display='none';
return false;
JS;
    }
}
