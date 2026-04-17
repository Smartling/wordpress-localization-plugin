<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class Shortcode extends Unknown
{
    public function getType(): string
    {
        return 'shortcode';
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys(['shortcode'])];
    }
}
