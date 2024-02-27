<?php

namespace Smartling\Helpers;

class PlaceholderHelper
{
    public const SMARTLING_PLACEHOLDER_MASK_START = '#sl-start#';
    public const SMARTLING_PLACEHOLDER_MASK_END = '#sl-end#';

    public function addPlaceholders(string $string): string
    {
        return self::SMARTLING_PLACEHOLDER_MASK_START . $string . self::SMARTLING_PLACEHOLDER_MASK_END;
    }

    public function hasPlaceholders(string $string): bool
    {
        return preg_match('~' . self::SMARTLING_PLACEHOLDER_MASK_START . '.+' . self::SMARTLING_PLACEHOLDER_MASK_END . '~', $string);
    }

    public function removePlaceholders(string $string): string
    {
        return preg_replace('~' . self::SMARTLING_PLACEHOLDER_MASK_START . '(.*?)' . self::SMARTLING_PLACEHOLDER_MASK_END . '~', '$1', $string);
    }
}
