<?php

namespace Smartling\ContentTypes;

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
     * @param string $manager
     */
    public static function register(ContainerBuilder $di, string $manager = 'content-type-descriptor-manager'): void
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

    public function isVisible(string $page): bool
    {
        return $page === 'submissionBoard';
    }

    public function registerFilters(): void
    {
        $di = $this->getContainerBuilder();

        $cloneWrapperId = 'cloned-content.menu_item_meta';
        $definition = $di->register($cloneWrapperId, 'Smartling\Helpers\MetaFieldProcessor\CloneValueFieldProcessor');
        $definition
            ->addArgument('^(_menu_item_object|_menu_item_classes|_menu_item_type)$')
            ->addArgument($di->getDefinition('content.helper'));

        $di->get('meta-field.processor.manager')->registerProcessor($di->get($cloneWrapperId));
    }
}
