<?php

namespace Smartling;


trait DebugTrait
{
    /**
     * Displays the given data
     * @param mixed $data
     * @param bool  $die if true further script execution is stopped
     */
    public static function DebugPrint($data, $die = false)
    {
        echo '<pre>' . htmlentities(var_export($data, true)) . '</pre>';
        if (true === $die) {
            wp_die('Execution terminated due to debug purposes.');
        }
    }

    /**
     * Last chance to know what had happened if Wordpress is down.
     */
    public function shutdownHandler()
    {
        $logger = Bootstrap::getLogger();

        $skipLogging = E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING | E_STRICT | E_DEPRECATED;

        $loggingPattern = E_ALL ^ $skipLogging;

        $data = error_get_last();

        /**
         * @var int $errorType
         */
        $errorType = &$data['type'];

        if ($errorType & $loggingPattern) {
            $message = "An Error (0x{$data['type']}) occurred and Wordpress is down.\n";
            $message .= "Message: '{$data['message']}'\n";
            $message .= "Location: '{$data['file']}:{$data['line']}'\n";
            $logger->emergency($message);
        }
    }
}