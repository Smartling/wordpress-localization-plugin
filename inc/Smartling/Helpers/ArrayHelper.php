<?php

namespace Smartling\Helpers;

use Smartling\Settings\Locale;

class ArrayHelper
{
    /**
     * Retrieves the value of an array element or object property with the given key or property name.
     * If the key does not exist in the array or object, the default value will be returned instead.
     * The key may be specified in a dot format to retrieve the value of a sub-array or the property
     * of an embedded object. In particular, if the key is `x.y.z`, then the returned value would
     * be `$array['x']['y']['z']` or `$array->x->y->z` (if `$array` is an object). If `$array['x']`
     * or `$array->x` is neither an array nor an object, the default value will be returned.
     * Note that if the array already has an element `x.y.z`, then its value will be returned
     * instead of going through the sub-arrays.
     * Below are some usage examples,
     * ~~~
     * // working with array
     * $username = \yii\helpers\ArrayHelper::getValue($_POST, 'username');
     * // working with object
     * $username = \yii\helpers\ArrayHelper::getValue($user, 'username');
     * // working with anonymous function
     * $fullName = \yii\helpers\ArrayHelper::getValue($user, function ($user, $defaultValue) {
     *     return $user->firstName . ' ' . $user->lastName;
     * });
     * // using dot format to retrieve the property of embedded object
     * $street = \yii\helpers\ArrayHelper::getValue($users, 'address.street');
     * ~~~
     *
     * @param array|object    $array   array or object to extract value from
     * @param string|\Closure $key     key name of the array element, or property name of the object,
     *                                 or an anonymous function returning the value. The anonymous function signature
     *                                 should be:
     *                                 `function($array, $defaultValue)`.
     * @param mixed           $default the default value to be returned if the specified array key does not exist. Not
     *                                 used when getting value from an object.
     *
     * @return mixed the value of the element if found, default value otherwise
     */
    public static function getValue(array|object $array, string|\Closure $key, mixed $default = null): mixed
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (($pos = strrpos($key, '.')) !== false) {
            $array = static::getValue($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }

        if (is_object($array)) {
            return $array->$key;
        }

        if (is_array($array)) {
            return array_key_exists($key, $array) ? $array[$key] : $default;
        }

        return $default;
    }

    /**
     * Removes an item from an array and returns the value. If the key does not exist in the array, the default value
     * will be returned instead.
     * Usage examples,
     * ~~~
     * // $array = ['type' => 'A', 'options' => [1, 2]];
     * // working with array
     * $type = \yii\helpers\ArrayHelper::remove($array, 'type');
     * // $array content
     * // $array = ['options' => [1, 2]];
     * ~~~
     *
     * @param array  $array   the array to extract value from
     * @param string $key     key name of the array element
     * @param mixed  $default the default value to be returned if the specified key does not exist
     *
     * @return mixed|null the value of the element if found, default value otherwise
     */
    public static function remove(array &$array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key]) || array_key_exists($key, $array)) {
            $value = $array[$key];
            unset($array[$key]);

            return $value;
        }

        return $default;
    }

    /**
     * @return bool true if given value is an array, and it is not empty
     */
    public static function notEmpty(mixed $value): bool
    {
        return is_array($value) && 0 < count($value);
    }

    public static function first(array $array): mixed
    {
        if (!static::notEmpty($array)) {
            return false; // as reset() does
        }

        return $array[array_key_first($array)];
    }

    public static function last(array $array): mixed
    {
        if (!static::notEmpty($array)) {
            return false; // as reset() does
        }

        return $array[array_key_last($array)];
    }

    public static function sortLocales(array &$locales): void
    {
        usort($locales, static function (Locale $a, Locale $b) {
            if ($a->getLabel() === $b->getLabel()) {
                return 0;
            }
            return $a->getLabel() > $b->getLabel() ? 1 : -1;
        });
    }

    public static function toArrayOfIntegers(array $array, ?string $errorMessage): array
    {
        return array_map(static function($value) use ($errorMessage) {
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException($errorMessage ?: 'Value expected to be numeric, got ' . $value);
            }
            return (int)$value;
        }, $array);
    }
}
