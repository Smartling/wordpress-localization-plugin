<?php

namespace Smartling\ContentTypes;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypePage
 * @package Smartling\ContentTypes
 */
class ContentTypePage extends PostBasedContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'page';

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
     * @param ContainerBuilder $di
     * @param string           $manager
     */
    public static function register(ContainerBuilder $di, $manager = 'content-type-descriptor-manager')
    {
        $descriptor = new static($di);
        $mgr = $di->get($manager);
        /**
         * @var \Smartling\ContentTypes\ContentTypeManager $mgr
         */
        $mgr->addDescriptor($descriptor);
    }

    /**
     * Handler to register Widget (Edit Screen)
     * @return void
     */
    public function registerWidgetHandler()
    {
        $di = $this->getContainerBuilder();

        $definition = $di
            ->register('wp.page', 'Smartling\WP\Controller\PostBasedWidgetControllerStd')
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('entity.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            ->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')])
            ->addMethodCall('setAbilityNeeded', ['edit_post'])
            ->addMethodCall('setServedContentType', [$this->getSystemName()])
            ->addMethodCall('setNoOriginalFound', [__('No original page found')]);

        $di->getDefinition('manager.register')->addMethodCall('addService', [$definition]);
    }

    /**
     * Handler to register IO Wrapper
     * @return void
     */
    public function registerIOWrapper()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, 'Smartling\DbAl\WordpressContentEntities\PostEntityStd');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($this->getSystemName())
            // Page doesn't have related types like Posts
            ->addArgument([]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

    }

    /**
     * @return void
     */
    public function registerFilters()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'referenced-content.page_parent';
        $definition = $di->register($wrapperId, 'Smartling\Helpers\MetaFieldProcessor\ReferencedPostBasedContentProcessor');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('translation.helper'))
            ->addArgument('post_parent')
            ->addMethodCall('setContentHelper', [$di->getDefinition('content.helper')]);


        $di->get('meta-field.processor.manager')->registerProcessor($di->get($wrapperId));
    }
}
