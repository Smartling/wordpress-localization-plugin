<?php

namespace Smartling\ContentTypes;

use Smartling\ContentTypes\ConfigParsers\TermTypeConfigParser;
use Smartling\Helpers\StringHelper;
use Smartling\WP\Controller\ContentEditJobController;
use Smartling\WP\Controller\TaxonomyWidgetController;

/**
 * Class CustomTaxonomyType
 * @package Smartling\ContentTypes
 */
class CustomTaxonomyType extends TermBasedContentTypeAbstract
{
    use CustomTypeTrait;

    public function validateConfig()
    {
        $config = $this->getConfig();

        if (array_key_exists('taxonomy', $config)) {
            $this->validateType();
        }
    }

    private function validateType()
    {
        $validator = new TermTypeConfigParser($this->getConfig());

        if (!StringHelper::isNullOrEmpty($validator->getIdentifier())) {
            $this->setSystemName($validator->getIdentifier());
            $label = parent::getLabel();
            if ('unknown' === $label) {
                $label = $this->getSystemName();
            }
            $this->setLabel($label);
        }

        $this->setConfigParser($validator);
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
        if ($this->getConfigParser()->hasWidget()) {
            $di = $this->getContainerBuilder();
            $tag = 'wp.taxonomy.' . static::getSystemName();
            $di
                ->register($tag, TaxonomyWidgetController::class)
                ->addArgument($di->getDefinition('api.wrapper.with.retries'))
                ->addArgument($di->getDefinition('multilang.proxy'))
                ->addArgument($di->getDefinition('plugin.info'))
                ->addArgument($di->getDefinition('manager.settings'))
                ->addArgument($di->getDefinition('site.helper'))
                ->addArgument($di->getDefinition('manager.submission'))
                ->addArgument($di->getDefinition('site.cache'))
                ->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')])
                ->addMethodCall('setTaxonomy', [static::getSystemName()]);
            $di->get($tag)->register();
            $this->registerJobWidget();
        }
    }

    protected function registerJobWidget()
    {
        $di = $this->getContainerBuilder();
        $tag = 'wp.job.' . static::getSystemName();

        $di
            ->register($tag, ContentEditJobController::class)
            ->addArgument($di->getDefinition('api.wrapper.with.retries'))
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('manager.settings'))
            ->addArgument($di->getDefinition('site.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            ->addMethodCall('setServedContentType', [static::getSystemName()])
            ->addMethodCall('setBaseType', ['taxonomy']);
        $di->get($tag)->register();
    }
}
