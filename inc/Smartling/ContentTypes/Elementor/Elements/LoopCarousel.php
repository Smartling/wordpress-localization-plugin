<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\Elementor\ElementorQueryRelated;
use Smartling\Models\RelatedContentInfo;

class LoopCarousel extends Unknown {
    public function getType(): string
    {
        return 'loop-carousel';
    }

    public function getRelated(): RelatedContentInfo
    {
        return (new ElementorQueryRelated())->addLoopRelated(parent::getRelated(), $this->settings, $this->id);
    }
}
