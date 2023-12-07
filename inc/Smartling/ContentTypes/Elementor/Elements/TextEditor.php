<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class TextEditor extends Unknown {
    public function getType(): string
    {
        return 'text-editor';
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        if (array_key_exists('editor', $this->settings)) {
            $return['editor'] = $this->settings['editor'];
        }

        return [$this->getId() => $return];
    }
}
