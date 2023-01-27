<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypeNavigationMenuItem
 * @package Smartling\ContentTypes
 */
class ContentTypeNavigationMenuItem extends PostBasedContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'nav_menu_item';

    /**
     * ContentTypeNavigationMenuItem constructor.
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
    public static function register(ContainerBuilder $di, $manager = 'content-type-descriptor-manager'): void
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
            ->addArgument($this->getSystemName())
            ->addArgument([]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

    }

    public function getVisibility(): array
    {
        return [
            'submissionBoard' => true,
            'bulkSubmit' => false,
        ];
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'referenced-content.menu_item_parent';
        $definition = $di->register($wrapperId, 'Smartling\Helpers\MetaFieldProcessor\ReferencedContentProcessor');
        $definition
            ->addArgument($di->getDefinition('translation.helper'))
            ->addArgument(CustomMenuContentTypeHelper::META_KEY_MENU_ITEM_PARENT)
            ->addArgument($this->getSystemName())
            ->addMethodCall('setContentHelper', [$di->getDefinition('content.helper')]);

        $di->get('meta-field.processor.manager')->registerProcessor($di->get($wrapperId));


        $referenceWrapperId = 'referenced-content.menu_item_content';
        $definition = $di->register($referenceWrapperId, 'Smartling\Helpers\MetaFieldProcessor\NavigationMenuItemProcessor');
        $definition
            ->addArgument($di->getDefinition('translation.helper'))
            ->addArgument('^(_menu_item_object_id)$')
            ->addArgument($this->getSystemName())
            ->addMethodCall('setContentHelper', [$di->getDefinition('content.helper')]);

        $di->get('meta-field.processor.manager')->registerProcessor($di->get($referenceWrapperId));


        $cloneWrapperId = 'cloned-content.menu_item_meta';
        $definition = $di->register($cloneWrapperId, 'Smartling\Helpers\MetaFieldProcessor\CloneValueFieldProcessor');
        $definition
            ->addArgument('^(_menu_item_object|_menu_item_classes|_menu_item_type)$')
            ->addArgument($di->getDefinition('content.helper'));

        $di->get('meta-field.processor.manager')->registerProcessor($di->get($cloneWrapperId));
    }
}
