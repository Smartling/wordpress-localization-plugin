<?php

namespace Smartling\Helpers;

/**
 * Class DiagnosticsHelper
 *
 * @package Smartling\Helpers
 */
class DiagnosticsHelper
{

    /**
     * Flag that indicates that plugin functionality is blocked
     *
     * @var bool
     */
    private static $pluginBlocked = false;

    /**
     * Error messages
     *
     * @var array
     */
    private static $messages = [];

    /**
     * @param string $message
     * @param bool   $blockPlugin
     */
    public static function addDiagnosticsMessage($message, $blockPlugin = false)
    {
        if (is_string($message)) {
            static::$messages[] = $message;
            if (true === $blockPlugin) {
                self::$pluginBlocked = true;
            }
        }
    }

    /**
     * Returns the block flag value
     *
     * @return bool
     */
    public static function isBlocked()
    {
        return (bool)self::$pluginBlocked;
    }

    public static function populateErrorsToWordpress()
    {
        $messages = static::getMessages();

        if (0 < count($messages)) {
            global $error;

            if (!($error instanceof \WP_Error)) {
                $error = new \WP_Error();
                foreach ($messages as $message) {
                    $error->add('smartling', $message);
                }
            }
        }

    }

    /**
     * Returns error messages array
     *
     * @return array
     */
    public static function getMessages()
    {
        return self::$messages;
    }

    public static function reset()
    {
        self::$messages = [];
    }
}