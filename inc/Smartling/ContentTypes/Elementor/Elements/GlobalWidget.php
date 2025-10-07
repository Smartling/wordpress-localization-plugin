<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class GlobalWidget extends Unknown {
    public function getType(): string
    {
        return 'global';
    }

    public function getRelated(): RelatedContentInfo
    {
        $result = parent::getRelated();
        $id = $this->getIntSettingByKey('templateID', $this->raw);
        if ($id !== null) {
            $result->addContent(new Content($id, ContentTypeHelper::CONTENT_TYPE_POST), $this->id, 'templateID');
        }

        return $result;
    }

    public function getTranslatableStrings(): array
    {
        return [];
    }
}
