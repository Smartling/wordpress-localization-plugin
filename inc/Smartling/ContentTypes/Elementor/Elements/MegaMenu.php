<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class MegaMenu extends Unknown {
    public function getType(): string
    {
        return 'mega-menu';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();

        foreach ([
            'menu_item_icon/value/id',
            'menu_item_icon_active/value/id',
            'menu_toggle_icon_normal/value/id',
            'menu_toggle_icon_active/value/id',
        ] as $key) {
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
        }

        foreach ($this->settings['menu_items'] ?? [] as $index => $item) {
            if (isset($item[self::SETTING_KEY_DYNAMIC]) && is_array($item[self::SETTING_KEY_DYNAMIC])) {
                $path = "settings/menu_items/$index/" . self::SETTING_KEY_DYNAMIC;
                foreach ($this->getRelatedFromDynamic($item[self::SETTING_KEY_DYNAMIC], $path) as $relatedItem) {
                    $return->addContent($relatedItem->getContent(), $relatedItem->getContainerId(), $relatedItem->getPath());
                }
            }
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        $return = parent::getTranslatableStrings();

        if (array_key_exists('menu_name', $this->settings)) {
            $return[$this->id]['menu_name'] = $this->settings['menu_name'];
        }

        foreach ($this->settings['menu_items'] ?? [] as $item) {
            if (!array_key_exists('_id', $item)) {
                $this->getLogger()->warning("Missing _id for menu item in $this->id");
                continue;
            }
            $key = "menu_items/{$item['_id']}";
            if (array_key_exists('item_title', $item)) {
                $return[$this->id][$key]['item_title'] = $item['item_title'];
            }
        }

        return $return;
    }

    public function setTargetContent(
        ExternalContentElementor $externalContentElementor,
        RelatedContentInfo $info,
        array $strings,
        SubmissionEntity $submission,
    ): static {
        $this->raw = parent::setTargetContent($externalContentElementor, $info, $strings, $submission)->toArray();
        $this->settings = $this->raw['settings'] ?? [];

        foreach ($strings[$this->id] ?? [] as $array) {
            if (is_array($array)) {
                foreach ($array as $id => $values) {
                    foreach ($this->settings['menu_items'] ?? [] as $index => $item) {
                        if (($item['_id'] ?? '') === $id) {
                            foreach ($values as $property => $value) {
                                $this->settings['menu_items'][$index][$property] = $value;
                            }
                        }
                    }
                }
            }
        }
        $this->raw['settings'] = $this->settings;

        return new static($this->raw);
    }
}
