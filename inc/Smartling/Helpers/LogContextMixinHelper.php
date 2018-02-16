<?php

namespace Smartling\Helpers;

/**
 * Class LogContextMixinHelper
 * @package Smartling\Helpers
 */
class LogContextMixinHelper
{
    /**
     * @var array
     */
    private static $mixin = [];

    /**
     * @param string $key
     * @param mixed  $value
     */
    public static function addToContext($key, $value)
    {
        static::$mixin[$key] = $value;
    }

    /**
     * @param string $key
     */
    public static function removeFromContext($key)
    {
        if (array_key_exists($key, self::$mixin)) {
            unset(static::$mixin[$key]);
        }
    }

    public static function getContextMixin()
    {
        return self::$mixin;
    }
}