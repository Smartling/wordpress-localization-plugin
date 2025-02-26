<?php

namespace Smartling\Helpers;

class UiMessageHelper
{
    private const string CACHE_KEY_PREFIX = 'smartling.ui.message.';
    public const string DISMISS_MESSAGE_ACTION = 'smartling_dismiss_message';

    public static function dismissMessage(): void
    {
        $cache = self::getCache();
        if (array_key_exists('hash', $_GET)) {
            $cache->set(self::CACHE_KEY_PREFIX . $_GET['hash'], true, 60 * 60 * 180);
        }
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
        return <<<JS
jQuery.post(ajaxurl + '?action=$action&hash=$hash');
this.parentNode.parentNode.style.display='none';
return false;
JS;
    }
}
