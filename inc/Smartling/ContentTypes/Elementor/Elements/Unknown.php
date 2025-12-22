<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\Element;
use Smartling\ContentTypes\Elementor\ElementAbstract;
use Smartling\ContentTypes\Elementor\ElementFactory;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class Unknown extends ElementAbstract {
    use LoggerSafeTrait;

    public function getRelated(): RelatedContentInfo
    {
        $return = new RelatedContentInfo();
        $keys = [
            'background_image/id',
            'background_overlay_image/id',
            'background_overlay_image_mobile/id',
        ];
        foreach ($this->elements as $element) {
            if ($element instanceof Element) {
                $return = $return->include($element->getRelated(), $this->id);
            }
        }

        foreach ($keys as $key) {
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
        }

        if (array_key_exists(ElementAbstract::SETTING_KEY_DYNAMIC, $this->settings)
            && is_array($this->settings[ElementAbstract::SETTING_KEY_DYNAMIC])
        ) {
            foreach ($this->getRelatedFromDynamic(
                $this->settings[ElementAbstract::SETTING_KEY_DYNAMIC],
                "settings/" . ElementAbstract::SETTING_KEY_DYNAMIC,
            ) as $item) {
                 $return->addContent($item->getContent(), $item->getContainerId(), $item->getPath());
             }
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        $return = [];
        $keys = ['background_image/alt', 'html', 'text', 'title'];
        foreach ($this->elements as $element) {
            if ($element instanceof Element) {
                $return[] = $element->getTranslatableStrings();
                $stringsFromCommonKeys = $this->getTranslatableStringsByKeys($keys, $element);
                if (count($stringsFromCommonKeys) > 0) {
                    $return[] = [$element->getId() => $stringsFromCommonKeys];
                }
            }
        }
        $return = count($return) > 0 ? (new ArrayHelper())->add(...$return) : $return;

        $return += $this->getTranslatableStringsByKeys($keys);

        return [$this->id => $return];
    }

    public function getType(): string
    {
        return ElementFactory::UNKNOWN_ELEMENT;
    }
}
