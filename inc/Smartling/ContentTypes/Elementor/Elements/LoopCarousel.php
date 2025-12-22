<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class LoopCarousel extends Unknown {
    public function getType(): string
    {
        return 'loop-carousel';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        $key = "template_id";
        $id = $this->getIntSettingByKey($key, $this->settings);
        if ($id !== null) {
            $return->addContent(new Content($id, ContentTypeHelper::CONTENT_TYPE_POST), $this->id, "settings/$key");
        }

        foreach ($this->settings['post_query_include_term_ids'] ?? [] as $index => $termId) {
            if (is_numeric($termId)) {
                $return->addContent(new Content((int)$termId, ContentTypeHelper::CONTENT_TYPE_TAXONOMY), $this->id, "settings/post_query_include_term_ids/$index");
            }
        }

        return $return;
    }
}
