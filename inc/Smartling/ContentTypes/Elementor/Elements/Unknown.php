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
        $keys = ['background_image/id'];
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
            $dynamicTagsManager = $this->getDynamicTagsManager();
            if ($dynamicTagsManager !== null) {
                foreach ($this->settings[ElementAbstract::SETTING_KEY_DYNAMIC] as $property => $value) {
                    try {
                        $relatedId = $dynamicTagsManager->parse_tag_text($value, [], function ($id, $name, $settings) {
                            if (is_array($settings) && array_key_exists(ElementAbstract::SETTING_KEY_POPUP, $settings)) {
                                return (int)$settings[ElementAbstract::SETTING_KEY_POPUP];
                            }

                            return null;
                        });
                        if ($relatedId !== null) {
                            $return->addContent(
                                new Content($relatedId, ContentTypeHelper::CONTENT_TYPE_UNKNOWN),
                                $this->id,
                                implode('/', ['settings', ElementAbstract::SETTING_KEY_DYNAMIC, $property]),
                            );
                        }
                    } catch (\Throwable $e) {
                        $this->getLogger()->notice("Failed to get related id for property=$property, tag=$value: {$e->getMessage()}");
                        continue;
                    }
                }
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
