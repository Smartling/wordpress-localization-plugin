<?php

namespace Smartling\Helpers;

/**
 * This helper is designed to make raw queries to database and get raw results for debug activities only.
 */
class RawDbQueryHelper
{
    public static function getTableName(string $tableName): string
    {
        if (in_array($tableName, self::getWpdb()->tables(), true)) {
            return self::getWpdb()->{$tableName};
        }

        return self::getWpdb()->base_prefix . $tableName;
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

    public static function query(string $query): ?array
    {
        return self::getWpdb()
                   ->get_results($query, ARRAY_A);
    }
}
