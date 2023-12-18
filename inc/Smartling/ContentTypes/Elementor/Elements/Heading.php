<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class Heading extends Unknown {
    public function getType(): string
    {
        return 'heading';
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys(['title'])];
    }
}
