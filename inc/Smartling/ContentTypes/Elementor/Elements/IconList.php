<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;

class IconList extends Unknown {
    public function getType(): string
    {
        return 'icon-list';
    }

    public function getRelated(): array
    {
        $return = [];
        if (array_key_exists('image', $this->settings) && array_key_exists('id', $this->settings['image'])) {
            $return['image/id'] = [ContentTypeHelper::POST_TYPE_ATTACHMENT => $this->settings['image']['id']];
        }

        return [$this->getId() => $return];
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        foreach ($this->settings['icon_list'] ?? [] as $index => $listItem) {
            $key = 'icon_list/' . $listItem['_id'] ?? $index;
            if (array_key_exists('text', $listItem)) {
                $return[$key]['text'] = $listItem['text'];
            }
        }

        return [$this->getId() => $return];
    }
}
