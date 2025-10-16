<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\ExternalContentElementor;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Submissions\SubmissionEntity;

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

        return $return;
    }
}
