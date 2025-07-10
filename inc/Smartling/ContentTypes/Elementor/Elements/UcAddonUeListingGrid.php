<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class UcAddonUeListingGrid extends Unknown
{
    use LoggerSafeTrait;

    public function getType(): string
    {
        return 'ucaddon_ue_listing_grid';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        $this->getLogger()->debug('Processing listing grid');
        $key = 'listing_template_templateid';
        if (is_numeric($this->settings[$key] ?? '')) {
            $id = (int)$this->settings[$key];
            $this->getLogger()->debug('Got numeric id: ' . $id);
            $return->addContent(new Content($id, ContentTypeHelper::CONTENT_TYPE_UNKNOWN), $this->id, "settings/$key");
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys([
            'no_posts_found',
        ])];
    }
}
