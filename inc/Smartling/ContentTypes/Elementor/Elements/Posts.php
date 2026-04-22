<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class Posts extends Unknown
{
    public function getType(): string
    {
        return 'posts';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        $key = 'custom_skin_template';
        $id = $this->getIntSettingByKey($key, $this->settings);
        if ($id !== null) {
            $return->addContent(new Content($id, ContentTypeHelper::CONTENT_TYPE_UNKNOWN), $this->id, "settings/$key");
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        return [$this->id => $this->getTranslatableStringsByKeys([
            'classic_read_more_text',
            'cards_read_more_text',
            'pagination_prev_label',
            'pagination_next_label',
            'text',
            'load_more_no_posts_custom_message',
            'loadmore_text',
            'loadmore_loading_text',
        ])];
    }
}
