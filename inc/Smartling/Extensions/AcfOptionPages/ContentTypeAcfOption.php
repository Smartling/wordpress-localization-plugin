<?php

namespace Smartling\Extensions\AcfOptionPages;

use Smartling\ContentTypes\ContentTypeAbstract;
use Smartling\ContentTypes\ContentTypeManager;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypeAcfOption
 */
class ContentTypeAcfOption extends ContentTypeAbstract
{

    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'acf_options';

    /**
     * Wordpress name of content-type, e.g.: post, page, post-tag
     * @return string
     */
    public function getSystemName()
    {
        return static::WP_CONTENT_TYPE;
    }

    /**
     * ContentTypeAcfOption constructor.
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
         * @var ContentTypeManager $mgr
         */
        $mgr->addDescriptor($descriptor);
    }

    /**
     * Display name of content type, e.g.: Post
     * @return string
     */
    public function getLabel()
    {
        return __('ACF Options Page');
    }

    /**
     * @return array [
     *  'submissionBoard'   => true|false,
     *  'bulkSubmit'        => true|false
     * ]
     */
    public function getVisibility()
    {
        return [
            'submissionBoard' => true,
            'bulkSubmit'      => true,
        ];
    }

    /**
     * Handler to register IO Wrapper
     * @return void
     */
    public function registerIOWrapper()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, 'Smartling\Extensions\AcfOptionPages\AcfOptionEntity');
        $definition
            ->addMethodCall('setDbal', [$di->getDefinition('site.db')]);

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
    public function getBaseType()
    {
        return 'virtual';
    }

    public function isVirtual()
    {
        return true;
    }
}