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
        $isConsole = !(function_exists('wp_die'));

        $content = var_export($data, true);

        if (!$isConsole) {
            $content = vsprintf('<pre>%s</pre>', [htmlentities($content)]);
        }

        if ($isConsole) {
            echo '######## Debug Print (Start) ########' . PHP_EOL;
        }
        echo $content;
        if ($isConsole) {
            echo PHP_EOL;
            echo '######## Debug Print (End)   ########' . PHP_EOL;
        }

        if (true === $die) {
            $message = 'Execution terminated due to debug purposes.';
            if ($isConsole) {
                die($message);
            } else {
                wp_die($message);
            }
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

        if (class_exists('\Kint')) {
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

    /**
     * @param bool $withDate
     * @param bool $forceDefault
     *
     * @return string
     */
    public static function getLogFileName($withDate = true, $forceDefault = false)
    {
        $container = self::getContainer();
        $pluginDir = $container->getParameter('plugin.dir');

        $paramName = true === $forceDefault
            ? 'logger.filehandler.standard.filename.default'
            : 'logger.filehandler.standard.filename';

        $filename = $container->getParameter($paramName);

        if (false === $withDate) {
            $fullFilename = vsprintf('%s', [str_replace('%plugin.dir%', $pluginDir, $filename)]);
        } else {
            $fullFilename = vsprintf('%s-%s', [str_replace('%plugin.dir%', $pluginDir, $filename), date('Y-m-d')]);
        }

        return $fullFilename;
    }

    /**
     * @return string
     */
    public static function getCurrentLogFileSize()
    {
        $logFile = self::getLogFileName();

        if (!file_exists($logFile) || !is_readable($logFile)) {
            return '0';
        }

        $size = filesize($logFile);

        return self::prettyPrintSize($size);
    }

    /**
     * @param int $size
     * @param int $stepForward
     * @param int $divider
     * @param int $precision
     *
     * @return string
     */
    public static function prettyPrintSize($size, $stepForward = 750, $divider = 1024, $precision = 2)
    {
        $scales = [
            'B' => 'B',
            'K' => 'kB',
            'M' => 'MB',
            'G' => 'GB',
            'T' => 'TB',
            'P' => 'PB',
            'E' => 'EB',
        ];

        $scale = reset($scales);

        while ($stepForward < $size) {
            $newSize = $size / $divider;
            $newScale = next($scales);

            if (false === $newScale) {
                break;
            }
            $size = $newSize;
            $scale = $newScale;
        }

        return vsprintf('%s %s', [round($size, $precision), $scale]);
    }

}