<?php

namespace Smartling\ContentTypes\Elementor\Elements4;

use Smartling\ContentTypes\Elementor\ElementAbstract4;
use Smartling\Models\RelatedContentInfo;

class EFormLabel extends ElementAbstract4
{
    public function getType(): string
    {
        return 'e-form-label';
    }

    public function getRelated(): RelatedContentInfo
    {
        $return = new RelatedContentInfo();
        foreach ($this->elements as $element) {
            $return = $return->include($element->getRelated(), $this->id);
        }
        return $return;
    }

    public function getTranslatableStrings(): array
    {
        $result = [];
        foreach (['text'] as $key) {
            $value = $this->extractTypedValue($this->settings[$key] ?? null);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        return [$this->id => $result];
    }
}
