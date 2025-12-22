<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class Video extends Unknown {
    public function getType(): string
    {
        return 'video';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        
        $id = $this->getIntSettingByKey('image_overlay/id', $this->settings);
        if ($id !== null) {
            $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, 'settings/image_overlay/id');
        }

        return $return;
    }
}
