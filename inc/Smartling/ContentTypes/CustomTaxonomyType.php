<?php

namespace Smartling\ContentTypes;

use Smartling\ContentTypes\ConfigParsers\TermTypeConfigParser;
use Smartling\Helpers\StringHelper;

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
        if ($this->getConfigParser()->hasWidget()) {
            $di = $this->getContainerBuilder();

            $di
                ->register('wp.taxonomy.' . static::getSystemName(), 'Smartling\WP\Controller\TaxonomyWidgetController')
                ->addArgument($di->getDefinition('logger'))
                ->addArgument($di->getDefinition('multilang.proxy'))
                ->addArgument($di->getDefinition('plugin.info'))
                ->addArgument($di->getDefinition('entity.helper'))
                ->addArgument($di->getDefinition('manager.submission'))
                ->addArgument($di->getDefinition('site.cache'))
                ->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')]);

            $di->get('wp.taxonomy.' . static::getSystemName())->register();

        }
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
    }
}