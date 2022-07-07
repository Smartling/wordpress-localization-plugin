<?php

namespace Smartling\Helpers;

class PlaceholderHelper
{
    public const SMARTLING_PLACEHOLDER_MASK_START = '#sl-start#';
    public const SMARTLING_PLACEHOLDER_MASK_END = '#sl-end#';

    public function buildPlaceholder($content): string
    {
        return self::SMARTLING_PLACEHOLDER_MASK_START . $content . self::SMARTLING_PLACEHOLDER_MASK_END;
    }
}
