<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Elementor\Core\DynamicTags\Manager;
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

    private const SETTING_KEY_POPUP = 'popup';
    private const SETTING_KEY_DYNAMIC = '__dynamic__';

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

        if (array_key_exists(self::SETTING_KEY_DYNAMIC, $this->settings)
            && is_string($this->settings[self::SETTING_KEY_DYNAMIC])
            && str_starts_with($this->settings[self::SETTING_KEY_DYNAMIC], '[' . Manager::TAG_LABEL)
        ) {
            $dynamicTagsManager = null;
            $managerPath = WP_PLUGIN_DIR . '/elementor/core/dynamic-tags/manager.php';
            if (file_exists($managerPath)) {
                try {
                    require_once $managerPath;
                    $dynamicTagsManager = new Manager();
                } catch (\Throwable $e) {
                    $this->getLogger()->notice('Unable to initialize Elementor dynamic tags manager, Elementor tags processing not available: ' . $e->getMessage());
                }
                $popupId = $dynamicTagsManager->parse_tag_text($this->settings[self::SETTING_KEY_DYNAMIC], [], function ($id, $name, $settings) {
                    if (is_array($settings) && array_key_exists(self::SETTING_KEY_POPUP, $settings)) {
                        return (int)$settings[self::SETTING_KEY_POPUP];
                    }

                    return null;
                });
                if ($popupId !== null) {
                    $return->addContent(new Content($popupId, ContentTypeHelper::CONTENT_TYPE_UNKNOWN), $this->id, 'settings/' . self::SETTING_KEY_DYNAMIC);
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
