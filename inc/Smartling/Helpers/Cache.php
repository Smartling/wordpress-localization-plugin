<?php

namespace Smartling\Helpers;

/**
 * Class Cache
 * @package Smartling\Helpers
 */
class Cache
{
    const GROUP = "smartling";

    /**
     * @param string $key
     * @param        $data
     * @param null   $expire
     * @param string $group
     *
     * @return bool
     */
    public function add($key, $data, $expire = null, $group = self::GROUP)
    {
        return wp_cache_add($key, $data, $group, $expire);
    }

    /**
     * @param string $key
     * @param        $data
     * @param null   $expire
     * @param string $group
     *
     * @return bool
     */
    public function set($key, $data, $expire = null, $group = self::GROUP)
    {
        return wp_cache_set($key, $data, $group, $expire);
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return bool|mixed
     */
    public function get($key, $group = self::GROUP)
    {
        return wp_cache_get($key, $group);
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return bool
     */
    public function delete($key, $group = self::GROUP)
    {
        return wp_cache_delete($key, $group);
    }

    public function flush()
    {
        wp_cache_flush();
    }
}