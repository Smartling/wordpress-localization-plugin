<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class Progress extends Unknown {
    public function getType(): string
    {
        return 'progress';
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys(['inner_text'])];
    }
}
