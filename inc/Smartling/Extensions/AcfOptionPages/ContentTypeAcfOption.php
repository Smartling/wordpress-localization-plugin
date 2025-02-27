<?php

namespace Smartling\Extensions\AcfOptionPages;

use Smartling\ContentTypes\ContentTypeAbstract;
use Smartling\ContentTypes\ContentTypeManager;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

class ContentTypeAcfOption extends ContentTypeAbstract
{
    public const string WP_CONTENT_TYPE = 'acf_options';

    public function getSystemName(): string
    {
        return static::WP_CONTENT_TYPE;
    }

    public function __construct(ContainerBuilder $di)
    {
        parent::__construct($di);

        $this->registerIOWrapper();
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

    public function getLabel(): string
    {
        return __('ACF Options Page');
    }

    public function registerIOWrapper(): void
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, AcfOptionEntity::class);
        $definition
            ->addArgument($di->getDefinition('site.db'));

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));
    }

    /**
     * Base type can be 'post' or 'term' used for Multilingual Press plugin.
     * @return string
     */
    public function getBaseType(): string
    {
        return 'virtual';
    }

    public function isVirtual(): bool
    {
        return true;
    }
}
