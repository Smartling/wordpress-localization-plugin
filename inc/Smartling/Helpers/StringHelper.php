<?php

namespace Smartling\Helpers;

/**
 * Class StringHelper
 *
 * @package Smartling\Helpers
 */
class StringHelper
{

    /**
     * Empty string constant
     */
    const EMPTY_STRING = '';

    /**
     * Checks if string is false null of empty
     *
     * @param $string
     *
     * @return bool
     */
    public static function isNullOrEmpty($string)
    {
        return in_array($string, [false, null, self::EMPTY_STRING]);
    }

    /**
     * @param string $pattern
     * @param string $borders
     * @param string $modifiers
     *
     * @return string
     */
    public static function buildPattern($pattern, $borders = '/', $modifiers = 'ius')
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

    /**
     * @param string $string
     * @param int    $maxLength
     * @param string $encoding
     * @param bool   $applyHtmlEncoding
     * @param string $wrapperTag
     *
     * @return string
     */
    public static function safeHtmlStringShrink($string, $maxLength = 50, $encoding = 'utf8', $applyHtmlEncoding = true, $wrapperTag = 'span')
    {
        if (mb_strlen($string, $encoding) > $maxLength) {
            $orig = $string;

            if (true === $applyHtmlEncoding) {
                $orig = htmlentities($orig);
            }

            $shrinked = mb_substr($orig, 0, $maxLength - 3, $encoding) . '...';

            $string = HtmlTagGeneratorHelper::tag($wrapperTag, $shrinked, ['title' => $orig]);
        }

        return $string;
    }

    public static function safeStringShrink($string, $maxLength = 50, $encoding = 'utf8', $applyHtmlEncoding = true)
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