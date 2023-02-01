<?php

namespace Smartling\ContentTypes;

use Smartling\Exception\SmartlingConfigException;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class UnregisteredContentTypeAbstract implements ContentTypeInterface
{
    public function isVisible(string $page): bool
    {
        return true;
    }

    public function isTaxonomy(): bool
    {
        return false;
    }

    public function isPost(): bool
    {
        return false;
    }

    public function isVirtual(): bool
    {
        return false;
    }

    public function forceDisplay(): bool
    {
        return false;
    }
}
