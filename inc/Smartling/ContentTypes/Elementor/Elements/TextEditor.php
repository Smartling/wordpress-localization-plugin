<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class TextEditor extends Unknown {
    public function getType(): string
    {
        return 'text-editor';
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys(['editor'])];
    }
}
