<?php

namespace Smartling\ContentTypes;

use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

class ContentTypeWidget extends ContentTypeAbstract
{
    public function __construct(ContainerBuilder $di)
    {
        parent::__construct($di);

        $this->registerIOWrapper();
        $this->registerWidgetHandler();
        $this->registerFilters();
    }

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
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'theme_widget';

    /**
     * Wordpress name of content-type, e.g.: post, page, post-tag
     * @return string
     */
    public function getSystemName(): string
    {
        return static::WP_CONTENT_TYPE;
    }

    /**
     * Display name of content type, e.g.: Post
     * @return string
     */
    public function getLabel(): string
    {
        return __('Theme Widget');
    }

    /**
     * Handler to register IO Wrapper
     * @return void
     */
    public function registerIOWrapper()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, 'Smartling\DbAl\WordpressContentEntities\WidgetEntity');
        $definition
            ->addArgument($this->getSystemName())
            ->addArgument([
                              ContentTypeNavigationMenu::WP_CONTENT_TYPE,
                              'attachment',
                          ]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));
    }

    /**
     * Handler to register Widget (Edit Screen)
     * @return void
     */
    public function registerWidgetHandler()
    {
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
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
