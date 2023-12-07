<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;

class Image extends Unknown {
    public function getType(): string
    {
        return 'image';
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
        if (array_key_exists('caption', $this->settings)) {
            $return['caption'] = $this->settings['caption'];
        }
        if (array_key_exists('image', $this->settings) && array_key_exists('alt', $this->settings['image'])) {
            $return['image/alt'] = $this->settings['image']['alt'];
        }

        return [$this->getId() => $return];
    }
}
