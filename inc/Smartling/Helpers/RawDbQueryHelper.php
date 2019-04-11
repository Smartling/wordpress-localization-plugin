<?php

namespace Smartling\Helpers;

/**
 * Class RawDbQueryHelper
 *
 * @package Smartling\Helpers
 *
 * This helper is designed to make raw queries to database and get raw results for debug activities only.
 */
class RawDbQueryHelper
{

    /**
     * @param string $tableName
     *
     * @return string
     */
    public static function getTableName($tableName)
    {
        if (in_array($tableName, self::getWpdb()->tables(), true)) {
            return self::getWpdb()->{$tableName};
        } else {
            return self::getWpdb()->base_prefix . $tableName;
        }
    }

    /**
     * @return \wpdb
     */
    private static function getWpdb()
    {
        /**
         * @var \wpdb $wpdb
         */
        global $wpdb;

        return $wpdb;
    }

    /**
     * @param string $query
     *
     * @return array
     */
    public static function query($query)
    {
        return self::getWpdb()
                   ->get_results($query, ARRAY_A);
    }

}