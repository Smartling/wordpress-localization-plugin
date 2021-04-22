<?php

namespace Smartling\ContentTypes;

abstract class ContentTypeVirtual extends ContentTypeAbstract
{
    public function isVirtual(): bool
    {
        return true;
    }

    public function getBaseType(): string
    {
        return 'virtual';
    }
}
