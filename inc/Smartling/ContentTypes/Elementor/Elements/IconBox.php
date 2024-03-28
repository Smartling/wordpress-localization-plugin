<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class IconBox extends Icon {
    public function getType(): string
    {
        return 'icon-box';
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys([
            'caption',
            'description_text',
            'image/alt',
            'title_text',
        ])];
    }
}
