<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class Icon extends Unknown {
    public function getType(): string
    {
        return 'icon';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = new RelatedContentInfo();
        $key = 'selected_icon/value/id';
        $id = $this->getIntSettingByKey($key, $this->settings);
        if ($id !== null) {
            $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys(['caption', 'image/alt'])];
    }

    private function getRelatedPaths(): array {

    }
}
