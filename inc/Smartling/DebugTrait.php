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

    public static function getRequestContext()
    {
        return [
            'get' => $_GET,
            'post' => $_POST,
        ];
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
     * Error types that actually terminate the request. Anything else - notices,
     * warnings, and in particular deprecations - is left alone: error_get_last()
     * returns the last error of *any* severity, so treating non-fatal types as
     * fatal reports an emergency on every otherwise healthy request and buries
     * the real crashes.
     */
    private static function fatalErrorTypes(): int
    {
        return E_ERROR
            | E_PARSE
            | E_CORE_ERROR
            | E_COMPILE_ERROR
            | E_USER_ERROR
            | E_RECOVERABLE_ERROR;
    }

    private static function errorTypeNames(): array
    {
        return [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];
    }

    public static function isFatalError(?int $errorType): bool
    {
        return $errorType !== null && ($errorType & self::fatalErrorTypes()) !== 0;
    }

    public static function getErrorTypeName(int $errorType): string
    {
        return self::errorTypeNames()[$errorType] ?? "UNKNOWN($errorType)";
    }

    /**
     * Last chance to know what had happened if Wordpress is down.
     */
    public function shutdownHandler()
    {
        $data = error_get_last();

        if (!self::isFatalError($data['type'] ?? null)) {
            return;
        }

        $message = sprintf(
            "A fatal error (%s) occurred and Wordpress is down.\nMessage: '%s'\nLocation: '%s:%s'\n",
            self::getErrorTypeName($data['type']),
            $data['message'],
            $data['file'],
            $data['line'],
        );

        Bootstrap::getLogger()->emergency($message);
    }
}
