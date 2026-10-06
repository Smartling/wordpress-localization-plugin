<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\ElementorQueryRelated;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class LoopGrid extends Unknown {
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

        return (new ElementorQueryRelated())->addRelated($return, $this->settings, $this->id, 'post_query_');
    }
}
