<?php

namespace Smartling\Helpers;

class LogContextMixinHelper
{
    private static array $mixin = [];
    private static array $mixinString = [];

    public static function addToContext(string $key, string|int $value): void
    {
        static::$mixin[$key] = $value;
    }

    public static function removeFromContext(string $key): void
    {
        if (array_key_exists($key, self::$mixin)) {
            unset(static::$mixin[$key]);
        }
    }

    public static function getContextMixin(): array
    {
        return self::$mixin;
    }

    public static function addToStringContext(string $key, string|int $value): void
    {
        static::$mixinString[$key] = $value;
    }

    public static function removeFromStringContext(string $key): void
    {
        unset(static::$mixinString[$key]);
    }

    public static function getStringContext(): string
    {
        $strings = [];
        foreach (static::$mixinString as $key => $value) {
            $strings[] = $key . '"' . addslashes($value) . '"';
        }

        return implode(', ', $strings);
    }
}
