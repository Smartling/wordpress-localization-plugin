<?php

namespace Smartling\ContentTypes;

use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Interface ContentTypeInterface
 * @package Smartling\ContentTypes
 */
interface ContentTypeInterface
{
    /**
     * constructor.
     *
     * @param ContainerBuilder $di
     */
    public function __construct(ContainerBuilder $di);

    /**
     * @return ContainerBuilder
     */
    public function getContainerBuilder();

    /**
     * @param ContainerBuilder $containerBuilder
     */
    public function setContainerBuilder(ContainerBuilder $containerBuilder);

    /**
     * Wordpress name of content-type, e.g.: post, page, post-tag
     * @return string
     */
    public function getSystemName();

    /**
     * Display name of content type, e.g.: Post
     * @return string
     */
    public function getLabel();

    /**
     * @return array [
     *  'submissionBoard'   => true|false,
     *  'bulkSubmit'        => true|false
     * ]
     */
    public function getVisibility();

    /**
     * @return bool
     */
    public function isTaxonomy();

    /**
     * @return bool
     */
    public function isPost();

    /**
     * @return bool
     */
    public function isVirtual();

    /**
     * Place to filters even if not registered in Wordpress
     * @return bool
     */
    public function forceDisplay();

    /**
     * Handler to register IO Wrapper
     * @return void
     */
    public function registerIOWrapper();

    /**
     * Handler to register Widget (Edit Screen)
     * @return void
     */
    public function registerWidgetHandler();

    /**
     * @return void
     */
    public function registerFilters();

    /**
     * Base type can be 'post' or 'term' used for Multilingual Press plugin.
     * @return string
     */
    public function getBaseType();
}