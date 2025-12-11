<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class ImageCarousel extends Unknown {
    public function getType(): string
    {
        return 'image-carousel';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        foreach ($this->settings['carousel'] ?? [] as $index => $listItem) {
            $key = "carousel/$index/id";
            $id = $this->getIntSettingByKey($key, $this->settings);
            if ($id !== null) {
                $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
            }
        }

        return $return;
    }
}
