<?php

namespace Smartling\Helpers\QueryBuilder;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;

/**
 * Helps to build CRUD SQL queries
 * Class QueryBuilder
 *
 * @package Smartling\Helpers
 */
class QueryBuilder
{
    /**
     * Exception code for empty field list
     */
    const EXCEPTION_CODE_EMPTY_FIELD_LIST = 4;


    /**
     * Validates sorting options
     *
     * @param array $fieldNames
     * @param array $sortOptions
     *
     * @throws \InvalidArgumentException
     * @return bool
     */
    public static function validateSortOptions(array $fieldNames, array $sortOptions = [])
    {
        if (empty($fieldNames)) {
            throw new \InvalidArgumentException(
                'Field list CANNOT be empty',
                static::EXCEPTION_CODE_EMPTY_FIELD_LIST
            );
        }

        $valid = true;

        $fieldValues = [
            SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_ASC,
            SmartlingToCMSDatabaseAccessWrapperInterface::SORT_OPTION_DESC,
        ];

        foreach ($sortOptions as $field => $order) {
            if (!in_array($field, $fieldNames) || !in_array($order, $fieldValues)) {
                $valid = false;
                break;
            }
        }

        return $valid;
    }

    /**
     * @param $pageOptions
     *
     * @return bool true or false
     */
    public static function validatePageOptions($pageOptions)
    {
        $valid = null;

        switch (true) {

            // no sorting enabled
            case is_null($pageOptions) :
                $valid = true;
                break;

            // some sorting enabled
            case is_array($pageOptions) :

                //array('limit' => 20, 'page' => 1)

                $validLimit = isset($pageOptions['limit']) && 0 < (int)$pageOptions['limit'];

                $validPage = isset($pageOptions['page']) && 0 < (int)$pageOptions['page'];

                $valid = $validLimit && $validPage;

                break;

            // not null or array
            default :
                $valid = false;
                break;
        }

        return $valid;
    }

    protected static $sqlFunctionNames = [
        'count',
        'group_concat',
    ];

    /**
     * @param string         $tableName
     * @param array          $fieldsList
     * @param ConditionBlock $conditions
     * @param array          $sortOptions
     * @param null|array     $pageOptions
     * @param null|array     $groupOptions
     *
     * @return string
     */
    public static function buildSelectQuery($tableName, $fieldsList, ConditionBlock $conditions = null, $sortOptions = [], $pageOptions = null, $groupOptions = null)
    {
        $fieldsString = static::buildFieldListString($fieldsList);

        $query = vsprintf(
            'SELECT %s FROM %s',
            [
                $fieldsString,
                static::escapeName($tableName),
            ]
        );

        if ($conditions instanceof ConditionBlock) {
            $query .= vsprintf(' WHERE %s', [(string)$conditions]);
        }

        $query .= static::buildGroupSubQuery($groupOptions);

        $query .= static::buildSortSubQuery($sortOptions);

        $query .= static::buildLimitSubQuery($pageOptions);

        return $query;
    }

    /**
     * @param                $tableName
     * @param ConditionBlock $conditions
     * @param null           $pageOptions
     *
     * @return string
     */
    public static function buildDeleteQuery($tableName, ConditionBlock $conditions = null, $pageOptions = null)
    {
        $query = vsprintf('DELETE FROM %s', [static::escapeName($tableName)]);

        if ($conditions instanceof ConditionBlock) {
            $query .= vsprintf(' WHERE %s', [(string)$conditions]);
        }

        $query .= static::buildLimitSubQuery($pageOptions, true);

        return $query;
    }

    /**
     * @param                $tableName
     * @param array          $fieldValueList
     * @param ConditionBlock $conditions
     * @param null           $pageOptions
     *
     * @return string
     */
    public static function buildUpdateQuery($tableName, array $fieldValueList, ConditionBlock $conditions = null, $pageOptions = null)
    {
        $template = 'UPDATE %s SET %s';

        $query = vsprintf($template, [
            static::escapeName($tableName),
            static::buildAssignmentSubQuery($fieldValueList),
        ]);

        if ($conditions instanceof ConditionBlock) {
            $query .= vsprintf(' WHERE %s', [(string)$conditions]);
        }

        if (null !== $pageOptions) {
            $query .= static::buildLimitSubQuery($pageOptions, true);
        }

        return $query;
    }

    /**
     * @param string $tableName
     * @param array  $fieldValueList
     * @param bool   $ignoreKeyDublicate
     *
     * @return string
     */
    public static function buildInsertQuery($tableName, array $fieldValueList, $ignoreKeyDublicate = false)
    {
        $template = 'INSERT %s INTO %s (%s) VALUES (%s)';

        $ignore = true === $ignoreKeyDublicate ? 'IGNORE' : '';

        $query = vsprintf($template, [
            $ignore,
            static::escapeName($tableName),
            static::buildFieldListString(array_keys($fieldValueList)),
            implode(',', array_map(function ($item) {
                return null === $item ? 'null' : "'{$item}'";
            }, static::escapeValues(array_values($fieldValueList)))),
        ]);

        return $query;
    }

    /**
     * @param array $fieldList
     *
     * @return string
     */
    public static function buildFieldListString(array $fieldList)
    {
        $prebuild = [];

        foreach ($fieldList as $field) {
            if (is_array($field)) {
                $keys = array_keys($field);
                $fld = reset($keys);
                $alias = end($field);
                $prebuild[] = static::escapeName($fld) . ' AS ' . static::escapeName($alias);
            } else {
                $prebuild[] = static::escapeName($field);
            }
        }

        return implode(', ', $prebuild);
    }

    /**
     * @param $sortOptions
     *
     * @return string
     */
    private static function buildSortSubQuery(array  $sortOptions)
    {
        $part = '';

        if (!empty($sortOptions)) {
            $preOptions = [];

            foreach ($sortOptions as $filed => $value) {
                $preOptions[] = vsprintf('`%s` %s', [
                    $filed,
                    $value,
                ]);
            }

            $part .= vsprintf(' ORDER BY %s', [implode(' , ', $preOptions)]);
        }

        return $part;
    }

    /**
     * @param array $groupOptions
     *
     * @return string
     */
    private static function buildGroupSubQuery($groupOptions)
    {
        $part = '';

        if (!is_array($groupOptions) || 0 === count($groupOptions)) {
            return $part;
        }

        $parts = [];

        foreach ($groupOptions as $element) {
            $parts[] = static::escapeName($element);
        }

        $part = implode(', ', $parts);

        return ' GROUP BY ' . $part;
    }

    /**
     * @param array $pageOptions
     * @param bool  $ignorePage (for update|delete purposes)
     *
     * @return string
     */
    private static function buildLimitSubQuery($pageOptions, $ignorePage = false)
    {
        $part = '';

        if (null !== $pageOptions) {
            $limit = (int)$pageOptions['limit'];
            if (true === $ignorePage) {
                $part .= vsprintf(' LIMIT %d', [$limit]);
            } else {
                $offset = (((int)$pageOptions['page']) - 1) * $limit;
                $part .= vsprintf(' LIMIT %d,%d', [
                    $offset,
                    $limit,
                ]);
            }
        }

        return $part;
    }

    /**
     * @param array $fieldValueList
     *
     * @return string
     */
    public static function buildAssignmentSubQuery(array $fieldValueList)
    {
        $subQueryParts = [];

        foreach ($fieldValueList as $column => $value) {
            $subQueryParts[] = vsprintf('%s = %s',
                                        [
                                            static::escapeName($column),
                                            '\'' . static::escapeValue($value) . '\'',
                                        ]);
        }

        return implode(', ', $subQueryParts);

    }

    /**
     * Escapes a value to be safe for SQL
     *
     * @param $value
     *
     * @return string
     */
    public static function escapeValue($value)
    {
        return addslashes(trim($value));
    }

    /**
     * Escapes an array of values to be safe for SQL
     *
     * @param array $values
     *
     * @return array
     */
    public static function escapeValues(array $values)
    {
        $result = [];

        foreach ($values as $value) {
            $result[] = static::escapeValue($value);
        }

        return $result;
    }

    /**
     * Escapes field name
     *
     * @param $fieldName
     *
     * @return string
     */
    public static function escapeName($fieldName)
    {
        $tmp = strtolower(trim($fieldName));

        $is_function = false;

        foreach (static::$sqlFunctionNames as $functionName) {
            $pos = strpos($tmp, $functionName . '(');

            if (false !== $pos && 0 === $pos) {
                $is_function = true;
                break;
            }
        }

        return $is_function ? $fieldName : vsprintf('`%s`', [$fieldName]);
    }
}
