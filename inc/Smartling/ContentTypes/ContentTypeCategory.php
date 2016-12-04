<?php

namespace Smartling\ContentTypes;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypeCategory
 * @package Smartling\ContentTypes
 */
class ContentTypeCategory extends TermBasedContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'category';

    public function getSystemName()
    {
        return self::WP_CONTENT_TYPE;
    }

    /**
     * @param ContainerBuilder $di
     * @param string           $manager
     */
    public static function register(ContainerBuilder $di, $manager = 'content-type-descriptor-manager')
    {
        $descriptor = new self($di);
        $mgr = $di->get($manager);
        /**
         * @var \Smartling\ContentTypes\ContentTypeManager $mgr
         */
        $mgr->addDescriptor($descriptor);
    }

    /**
     * ContentTypePost constructor.
     *
     * @param ContainerBuilder $di
     */
    public function __construct(ContainerBuilder $di)
    {
        parent::__construct($di);

        $this->registerIOWrapper();
        $this->registerWidgetHandler();
        $this->registerFilters();
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'referenced-content.category_parent';
        $definition = $di->register($wrapperId, 'Smartling\Helpers\MetaFieldProcessor\ReferencedContentProcessor');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('translation.helper'))
            ->addArgument('parent')
            ->addArgument($this->getSystemName())
            ->addMethodCall('setContentHelper', [$di->getDefinition('content.helper')]);

        $di->get('meta-field.processor.manager')->registerProcessor($di->get($wrapperId));
    }

    /**
     * Handler to register IO Wrapper
     * @return void
     */
    public function registerIOWrapper()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, 'Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($this->getSystemName())
            ->addArgument([]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));
    }

    /**
     * Handler to register Widget (Edit Screen)
     * @return void
     */
    public function registerWidgetHandler()
    {
    }
}