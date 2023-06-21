<?php

namespace Smartling\ContentTypes;

use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

class ContentTypeNavigationMenu extends TermBasedContentTypeAbstract
{
    public const WP_CONTENT_TYPE = 'nav_menu';

    public function __construct(ContainerBuilder $di)
    {
        parent::__construct($di);

        $this->registerIOWrapper();
    }

    /**
     * Alters DI container
     * @param ContainerBuilder $di
     * @param string $manager
     */
    public static function register(ContainerBuilder $di, string $manager = 'content-type-descriptor-manager'): void
    {
        $descriptor = new static($di);
        $mgr = $di->get($manager);
        if (!$mgr instanceof ContentTypeManager) {
            throw new \RuntimeException('ContentTypeManager expected to be ' . ContentTypeManager::class);
        }
        $mgr->addDescriptor($descriptor);
    }

    /**
     * Handler to register IO Wrapper. Alters DI container.
     */
    public function registerIOWrapper(): void
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, TaxonomyEntityStd::class );
        $definition
            ->addArgument($this->getSystemName())
            ->addArgument([ContentTypeNavigationMenuItem::WP_CONTENT_TYPE]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));
    }
}
