<?php

namespace Smartling\ContentTypes\Elementor\Elements;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class Testimonial extends Unknown {
    public function getType(): string
    {
        return 'testimonial';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        $key = 'testimonial_image/id';
        $id = $this->getIntSettingByKey($key, $this->settings);
        if ($id !== null) {
            $return->addContent(new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT), $this->id, "settings/$key");
        }

        return $return;
    }

    public function getTranslatableStrings(): array
    {
        return [$this->getId() => $this->getTranslatableStringsByKeys([
            'testimonial_content',
            'testimonial_job',
            'testimonial_name',
        ])];
    }
}
