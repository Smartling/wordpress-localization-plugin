<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;
use Smartling\Services\ContentRelationsDiscoveryService;

class GlobalWidget extends Unknown {
    public function getType(): string
    {
        return 'global';
    }

    public function getRelated(): RelatedContentInfo
    {
        $result = new RelatedContentInfo();
        $id = $this->getIntSettingByKey('templateID', $this->raw);
        if ($id !== null) {
            $result->addContent(new Content($id, ContentRelationsDiscoveryService::POST_BASED_PROCESSOR), $this->id, 'templateID');
        }

        return $result;
    }

    public function getTranslatableStrings(): array
    {
        return [];
    }
}
