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
use Smartling\Models\RelatedContentItem;

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

    /**
     * @return RelatedContentItem[]
     */
    public function getRelatedFromDynamic(array $dynamic, string $path): array
    {
        $return = [];
        $dynamicTagsManager = $this->getDynamicTagsManager();
        foreach ($dynamic as $property => $value) {
            try {
                $related = $dynamicTagsManager->parse_tag_text($value, [], function ($id, $name, $settings): ?Content {
                    if (is_array($settings)) {
                        return $this->getKnownDynamicContent($name, $settings);
                    }

                    return null;
                });
                if ($related !== null) {
                    $return[] = new RelatedContentItem($related, $this->id, "$path/$property");
                }
            } catch (\Throwable $e) {
                $this->getLogger()->notice("Failed to get related id for property=$property, tag=$value: {$e->getMessage()}");
                continue;
            }
        }

        return $return;
    }

    private function getKnownDynamicContent(string $name, array $settings): ?Content
    {
        if ($name === self::DYNAMIC_POPUP && array_key_exists(self::DYNAMIC_POPUP, $settings)) {
            return new Content((int)$settings[self::DYNAMIC_POPUP], ContentTypeHelper::CONTENT_TYPE_UNKNOWN);
        }
        if ($name === self::DYNAMIC_INTERNAL_URL && ($settings['type'] ?? '') === 'post' && array_key_exists('post_id', $settings)) {
            return new Content((int)$settings['post_id'], ContentTypeHelper::CONTENT_TYPE_POST);
        }
        if ($name === self::DYNAMIC_POST_FEATURED_IMAGE && array_key_exists('fallback', $settings) && array_key_exists('id', $settings['fallback'])) {
            return new Content((int)$settings['fallback']['id'], ContentTypeHelper::POST_TYPE_ATTACHMENT);
        }

        return null;
    }
}
