<?php

namespace Smartling\DbAl\WordpressContentEntities;

abstract class VirtualEntityAbstract extends EntityAbstract
{
    public function getContentTypeProperty(): string
    {
        return '';
    }

    public function getMetadata(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    protected function getNonCloneableFields(): array
    {
        return [$this->getPrimaryFieldName()];
    }

    public function getPrimaryFieldName(): string
    {
        return 'id';
    }

    public function setMetaTag($tagName, $tagValue, $unique = true): void
    {
    }
}
