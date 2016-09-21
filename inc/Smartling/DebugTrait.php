<?php

namespace Smartling;


trait DebugTrait
{
    /**
     * Displays the given data
     *
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

    public static function Backtrace()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        unset ($backtrace[0]);
        return array_reverse($backtrace);
    }

    public static function BacktracePrint()
    {
        $backtrace = debug_backtrace();
        unset ($backtrace[0]);

        if (class_exists('\Kint'))
        {
            \Kint::trace($backtrace);
            return;
        }
        
        $backtrace = array_reverse($backtrace);

        $template = '<table border="1" width="100%%"><tr><th>call #</th><th>Caller</th><th>Target</th></tr>%s</table>';

        $rows = '';
        foreach ($backtrace as $index => $item) {
            if (array_key_exists('file', $item)) {
                $who = $item['file'] . ':' . $item['line'];
            } else {
                $who = 'callback';
            }
            if (array_key_exists('class', $item)) {
                $what = $item['class'] . $item['type'] . $item['function'] . '()';
            } else {
                $what = $item['function'];
            }
            $rows .= vsprintf('<tr><td>%s</td><td>%s</td><td>%s</td></tr>', [$index, $who, $what]);
        }
        echo vsprintf($template, [$rows]);
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