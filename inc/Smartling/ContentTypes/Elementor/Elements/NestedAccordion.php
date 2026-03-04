<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

class NestedAccordion extends Unknown {
    public function getType(): string
    {
        return 'nested-accordion';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        foreach (['accordion_item_title_icon', 'accordion_item_title_icon_active'] as $iconField) {
            $key = "$iconField/value/id";
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
        foreach ($this->settings['items'] ?? [] as $index => $item) {
            $key = 'items/' . ($item['_id'] ?? $index);
            if (array_key_exists('item_title', $item)) {
                $return[$key]['item_title'] = $item['item_title'];
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
        $this->settings = $this->raw['settings'] ?? [];

        foreach ($strings[$this->id] ?? [] as $array) {
            if (is_array($array)) {
                foreach ($array as $id => $values) {
                    foreach ($this->settings['items'] ?? [] as $index => $item) {
                        if (($item['_id'] ?? '') === $id) {
                            foreach ($values as $property => $value) {
                                $this->settings['items'][$index][$property] = $value;
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
