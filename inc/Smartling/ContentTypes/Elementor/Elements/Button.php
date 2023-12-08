<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class Button extends Unknown {
    public function getType(): string
    {
        return 'button';
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys(['text'])];
    }
}
