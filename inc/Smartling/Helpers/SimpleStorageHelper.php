<?php

namespace Smartling\Helpers;

/**
 * Class SimpleStorageHelper
 *
 * @package Smartling\Helpers
 */
class SimpleStorageHelper
{

    /**
     * @param string $storageKey
     * @param mixed  $value
     *
     * @return bool
     */
    public static function set($storageKey, $value)
    {
        if (false === self::get($storageKey, false)) {
            $result = add_site_option($storageKey, $value);
        } else {
            $result = update_site_option($storageKey, $value);
        }

        return $result;
    }

    /**
     * @param string $storageKey
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public static function get($storageKey, $defaultValue = null)
    {
        return get_site_option($storageKey, $defaultValue);
    }

    /**
     * @param string $storageKey
     *
     * @return bool
     */
    public static function drop($storageKey)
    {
        return delete_site_option($storageKey);
    }
}