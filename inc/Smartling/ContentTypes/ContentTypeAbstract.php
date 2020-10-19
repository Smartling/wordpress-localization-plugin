<?php

namespace Smartling\ContentTypes;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypeAbstract
 * @package Smartling\ContentTypes
 */
abstract class ContentTypeAbstract implements ContentTypeInterface
{
    private $containerBuilder;

    /**
     * @return ContainerBuilder
     */
    public function getContainerBuilder()
    {
        return $this->containerBuilder;
    }

    /**
     * @param ContainerBuilder $containerBuilder
     */
    public function setContainerBuilder(ContainerBuilder $containerBuilder)
    {
        $this->containerBuilder = $containerBuilder;
    }

    /**
     * ContentTypeAbstract constructor.
     *
     * @param ContainerBuilder $di
     */
    public function __construct(ContainerBuilder $di)
    {
        $this->setContainerBuilder($di);
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
     * @return bool
     */
    public function isTaxonomy()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isPost()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isVirtual()
    {
        return false;
    }

    /**
     * Place to filters even if not registered in Wordpress
     * @return bool
     */
    public function forceDisplay()
    {
        return false;
    }
}