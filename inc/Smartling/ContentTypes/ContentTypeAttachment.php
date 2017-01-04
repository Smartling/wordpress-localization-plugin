<?php
namespace Smartling\ContentTypes;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypeAttachment
 * @package Smartling\ContentTypes
 */
class ContentTypeAttachment extends PostBasedContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'attachment';

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

    /**
     * @return void
     */
    public function registerFilters()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'referenced-content.media_file';
        $definition = $di->register($wrapperId, 'Smartling\Helpers\MetaFieldProcessor\ReferencedFileFieldProcessor');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('translation.helper'))
            ->addArgument('post_parent');

        $di->get('meta-field.processor.manager')->registerProcessor($di->get($wrapperId));
    }
}
