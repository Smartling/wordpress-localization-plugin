<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class IconList extends Unknown {
    public function getType(): string
    {
        return 'icon-list';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = new RelatedContentInfo();
        foreach ($this->settings['icon_list'] ?? [] as $index => $listItem) {
            $key = "icon_list/$index/selected_icon/value/id";
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        foreach ($this->settings['icon_list'] ?? [] as $index => $listItem) {
            $key = 'icon_list/' . ($listItem['_id'] ?? $index);
            if (array_key_exists('text', $listItem)) {
                $return[$key]['text'] = $listItem['text'];
            }
        }

        return [$this->getId() => $return];
    }
}
