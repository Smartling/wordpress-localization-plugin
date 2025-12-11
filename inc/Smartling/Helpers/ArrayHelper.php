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
     * @param array|object    $array     array or object to extract value from
     * @param string|\Closure $key       key name of the array element, or property name of the object,
     *                                   or an anonymous function returning the value. The anonymous function signature
     *                                   should be:
     *                                   `function($array, $defaultValue)`.
     * @param mixed           $default   the default value to be returned if the specified array key does not exist. Not
     *                                   used when getting value from an object.
     * @param string          $separator separator for nested keys
     * @return mixed the value of the element if found, default value otherwise
     */
    public static function getValue(array|object $array, string|\Closure $key, mixed $default = null, string $separator = '.'): mixed
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (($pos = strrpos($key, $separator)) !== false) {
            $array = static::getValue($array, substr($key, 0, $pos), $default, $separator);
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

    public function setValue(array $array, string $key, mixed $value, string $separator = '.'): array
    {
        $result = $array;
        $parts = explode($separator, $key);
        if (count($parts) > 1) {
            $index = array_shift($parts);
            if (!array_key_exists($index, $result)) {
                $result[$index] = [];
            }
            $result[$index] = $this->setValue($result[$index], implode($separator, $parts), $value, $separator);
        } else {
            $result[$key] = $value;
        }

        return $result;
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

    public function flatten(array $array, string $base = '', string $divider = '/'): array
    {
        $output = [];
        foreach ($array as $key => $value) {
            $key = '' === $base ? $key : "$base$divider$key";
            $output = $this->add($output, is_array($value) ? $this->flatten($value, $key, $divider) : [$key => $value]);
        }

        return $output;
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

    public function structurize(array $array, $divider = '/'): array
    {
        $result = [];

        foreach ($array as $key => $element) {
            $pathElements = explode($divider, $key);
            $pointer = &$result;
            for ($i = 0; $i < (count($pathElements) - 1); $i++) {
                if (!isset($pointer[$pathElements[$i]])) {
                    $pointer[$pathElements[$i]] = [];
                }
                $pointer = &$pointer[$pathElements[$i]];
            }
            $pointer[end($pathElements)] = $element;
        }

        return $result;
    }

    public static function toArrayOfIntegers(array $array, ?string $errorMessage = null): array
    {
        return array_map(static function($value) use ($errorMessage) {
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException($errorMessage ?: 'Value expected to be numeric, got ' . $value);
            }
            return (int)$value;
        }, $array);
    }

    public function add(...$arrays): array
    {
        $result = [];
        foreach ($arrays as $array) {
            $result += $array;
        }

        return $result;
    }
}
