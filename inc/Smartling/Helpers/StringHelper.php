<?php

namespace Smartling\Helpers;

class StringHelper
{
    private const EMPTY_STRING = '';

    /**
     * @param mixed $string
     */
    public static function isNullOrEmpty($string): bool
    {
        return in_array($string, [false, null, self::EMPTY_STRING], true);
    }

    public static function buildPattern(string $pattern, string $borders = '/', string $modifiers = 'ius'): string
    {
        return vsprintf(
            '%s%s%s%s',
            [
                $borders,
                $pattern,
                $borders,
                $modifiers,
            ]
        );
    }

    public static function safeHtmlStringShrink(string $string, int $maxLength = 50, string $encoding = 'utf8', bool $applyHtmlEncoding = true, string $wrapperTag = 'span'): string
    {
        if (true === $applyHtmlEncoding) {
            $string = htmlentities($string);
        }

        if (mb_strlen($string, $encoding) > $maxLength) {
            $shrinked = mb_substr($string, 0, $maxLength - 3, $encoding) . '...';

            $string = HtmlTagGeneratorHelper::tag($wrapperTag, $shrinked, ['title' => $string]);
        }

        return $string;
    }

    public static function safeStringShrink(string $string, int $maxLength = 50, string $encoding = 'utf8', bool $applyHtmlEncoding = true): string
    {
        if (mb_strlen($string, $encoding) > $maxLength) {
            $orig = $string;

            if (true === $applyHtmlEncoding) {
                $orig = htmlentities($orig);
            }

            $string = mb_substr($orig, 0, $maxLength - 3, $encoding) . '...';
        }

        return $string;
    }
}
