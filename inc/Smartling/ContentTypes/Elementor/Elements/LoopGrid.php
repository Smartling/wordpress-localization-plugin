<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\Elementor\ElementorQueryRelated;
use Smartling\Models\RelatedContentInfo;

class LoopGrid extends Unknown {
    public function getType(): string
    {
        return 'loop-grid';
    }

    public function getRelated(): RelatedContentInfo
    {
        return (new ElementorQueryRelated())->addLoopRelated(parent::getRelated(), $this->settings, $this->id);
    }
}
