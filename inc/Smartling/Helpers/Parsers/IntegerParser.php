<?php

namespace Smartling\Helpers\Parsers;

/**
 * Class IntegerParser
 * @package Smartling\Helpers\Parsers
 */
class IntegerParser
{
    /**
     * @param string  $value
     * @param integer $out
     *
     * @return bool
     */
    public static function tryParseString($value, & $out)
    {
        $result = false;
        if (is_string($value)) {
            $extractedValue = (int)$value;
            if ($value === (string)$extractedValue) {
                $out = $extractedValue;
                $result = true;
            }
        } elseif (is_numeric($value)) {
            $out = (int) $value;
        }

        return $result;
    }

    public static function integerOrDefault($value, $default)
    {
        $parsed = $default;
        self::tryParseString($value,$parsed);
        return $parsed;
    }
}