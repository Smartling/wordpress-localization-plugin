<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class IconList extends Unknown {
    public function getType(): string
    {
        return 'icon-list';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        foreach ($this->settings['icon_list'] ?? [] as $index => $listItem) {
            $key = "icon_list/$index/selected_icon/value/id";
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
            if (array_key_exists(self::SETTING_KEY_DYNAMIC, $listItem)) {
                foreach ($this->getRelatedFromDynamic(
                    $listItem[self::SETTING_KEY_DYNAMIC],
                    "settings/icon_list/$index/" . self::SETTING_KEY_DYNAMIC,
                ) as $item) {
                    $return->addContent($item->getContent(), $item->getContainerId(), $item->getPath());
                }
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

    public function setTargetContent(
        ExternalContentElementor $externalContentElementor,
        RelatedContentInfo $info,
        array $strings,
        SubmissionEntity $submission,
    ): static {
        $this->raw = parent::setTargetContent($externalContentElementor, $info, $strings, $submission)->toArray();
        foreach ($strings[$this->id]['icon_list'] ?? [] as $id => $setting) {
            if (is_array($setting) && array_key_exists('text', $setting)) {
                foreach ($this->raw['settings']['icon_list'] ?? [] as $key => $icon) {
                    if (($icon['_id'] ?? '') === $id) {
                        $this->raw['settings']['icon_list'][$key]['text'] = $setting['text'];
                        break;
                    }
                }
            }
        }

        return new self($this->raw);
    }
}
