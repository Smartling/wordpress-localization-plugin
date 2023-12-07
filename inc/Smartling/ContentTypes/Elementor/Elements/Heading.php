<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class Heading extends Unknown {
    public function getType(): string
    {
        return 'heading';
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        if (array_key_exists('title', $this->settings)) {
            $return['title'] = $this->settings['title'];
        }

        return [$this->getId() => $return];
    }
}
