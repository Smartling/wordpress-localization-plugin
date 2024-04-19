<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class Counter extends Unknown {
    public function getType(): string
    {
        return 'counter';
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys(['prefix', 'suffix', 'title'])];
    }
}
