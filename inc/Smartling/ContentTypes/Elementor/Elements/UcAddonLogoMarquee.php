<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class UcAddonLogoMarquee extends Unknown
{
    public function getType(): string
    {
        return 'ucaddon_logo_marquee';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        foreach ($this->settings['uc_items'] ?? [] as $index => $item) {
            if (($item['image']['source'] ?? '') !== 'library') {
                continue;
            }
            $key = "uc_items/$index/image/id";
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content((int)$id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
        }

        return $return;
    }
}
