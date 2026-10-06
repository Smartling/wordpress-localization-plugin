<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\ElementorQueryRelatedTrait;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class LoopGrid extends Unknown {
    use ElementorQueryRelatedTrait;

    public function getType(): string
    {
        return 'loop-grid';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        $key = "template_id";
        $id = $this->getIntSettingByKey($key, $this->settings);
        if ($id !== null) {
            $return->addContent(new Content($id, ContentTypeHelper::CONTENT_TYPE_POST), $this->id, "settings/$key");
        }

        return $this->addQueryRelated($return, 'post_query_');
    }
}
