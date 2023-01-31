<?php

namespace Smartling\ContentTypes;

use Smartling\Exception\SmartlingConfigException;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class ContentTypeAbstract implements ContentTypeInterface
{
    private ContainerBuilder $containerBuilder;

    public function getContainerBuilder(): ContainerBuilder
    {
        return $this->containerBuilder;
    }

    public function __construct(ContainerBuilder $di)
    {
        $this->containerBuilder = $di;
    }

    public function isVisible(string $page): bool
    {
        return true;
    }

    public static function register(ContainerBuilder $di, string $manager = 'content-type-descriptor-manager'): void
    {
        $descriptor = new static($di);
        $mgr = $di->get($manager);
        if (!$mgr instanceof ContentTypeManager) {
            throw new SmartlingConfigException(ContentTypeManager::class . ' expected');
        }
        $mgr->addDescriptor($descriptor);
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
