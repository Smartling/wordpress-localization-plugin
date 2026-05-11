<?php

namespace Smartling\ContentTypes\Elementor\Elements4;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\ContentTypes\Elementor\ElementAbstract4;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

class EImage extends ElementAbstract4
{
    private const IMAGE_ID_PATH = 'image/value/src/value/id/value';

    public function getType(): string
    {
        return 'e-image';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = new RelatedContentInfo();
        foreach ($this->elements as $element) {
            $return = $return->include($element->getRelated(), $this->id);
        }
        $id = $this->getIntSettingByKey(self::IMAGE_ID_PATH, $this->settings);
        if ($id !== null) {
            $return->addContent(
                new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT),
                $this->id,
                'settings/' . self::IMAGE_ID_PATH,
            );
        }
        return $return;
    }

    public function getTranslatableStrings(): array
    {
        return [$this->id => []];
    }
}
