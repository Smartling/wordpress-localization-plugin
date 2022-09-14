<?php

namespace Smartling\Helpers;

/**
 * Class UiMessageHelper
 *
 * @package Smartling\Helpers
 */
class UiMessageHelper
{
    public static function displayMessages()
    {
        $type = 'error';
        $messages = DiagnosticsHelper::getMessages();
        if (0 < count($messages)) {
            $msg = '';
            foreach ($messages as $message) {
                $msg .= vsprintf('<div class="%s"><h4 style="margin-bottom: 0; padding-bottom: 0">Smartling connector:</h4><p>%s</p></div>', [$type, $message]);
            }
            echo $msg;
            DiagnosticsHelper::reset();
        }
    }
}