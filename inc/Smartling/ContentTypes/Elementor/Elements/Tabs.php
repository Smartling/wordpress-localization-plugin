<?php

namespace Smartling\ContentTypes\Elementor\Elements;

class Tabs extends Unknown {
    public function getType(): string
    {
        return 'tabs';
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        $knownRepeaterContent = ['tab_title', 'tab_content'];
        foreach ($this->settings['tabs'] ?? [] as $index => $tab) {
            $key = 'tabs/' . ($tab['_id'] ?? $index);
            foreach ($knownRepeaterContent as $field) {
                if (array_key_exists($field, $tab)) {
                    $return[$key][$field] = $tab[$field];
                }
            }
        }

        return [$this->getId() => $return];
    }
}
