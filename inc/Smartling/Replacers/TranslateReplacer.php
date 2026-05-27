<?php

namespace Smartling\Replacers;

class TranslateReplacer extends DoNothingContentReplacer
{
    public function getLabel(): string
    {
        return 'Translate';
    }
}
