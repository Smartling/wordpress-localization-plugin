<?php

namespace Smartling\ContentTypes;

use Smartling\ContentTypes\ConfigParsers\PostTypeConfigParser;
use Smartling\Helpers\StringHelper;
use Smartling\WP\Controller\PostBasedWidgetControllerStd;
use Smartling\WP\Controller\ContentEditJobController;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;

class CustomPostType extends PostBasedContentTypeAbstract
{
    use CustomTypeTrait;

    public function validateConfig(): void
    {
        $config = $this->getConfig();
        if (array_key_exists('type', $config)) {
            $this->validateType();
        }
    }

    private function validateType(): void
    {
        $parser = new PostTypeConfigParser($this->getConfig());

        if (!StringHelper::isNullOrEmpty($parser->getIdentifier())) {
            $this->setSystemName($parser->getIdentifier());
            /** @noinspection MissUsingParentKeywordInspection */
            $label = parent::getLabel();
            if ('unknown' === $label) {
                $label = $this->getSystemName();
            }
            $this->setLabel($label);
        }

        $this->setConfigParser($parser);
    }

    /**
     * Handler to register IO Wrapper. Alters DI container.
     */
    public function registerIOWrapper(): void
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, PostEntityStd::class);
        $definition
            ->addArgument($this->getSystemName())
            ->addArgument($this->getRelatedTaxonomies());
        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));
    }

    /**
     * Handler to register Widget (Edit Screen). Alters DI container.
     */
    public function registerWidgetHandler(): void
    {
        if ($this->getConfigParser()->hasWidget()) {
            $di = $this->getContainerBuilder();
            $tag = 'wp.' . $this->getSystemName();
            $di
                ->register($tag, PostBasedWidgetControllerStd::class)
                ->addArgument($di->getDefinition('api.wrapper.with.retries'))
                ->addArgument($di->getDefinition('multilang.proxy'))
                ->addArgument($di->getDefinition('plugin.info'))
                ->addArgument($di->getDefinition('manager.settings'))
                ->addArgument($di->getDefinition('site.helper'))
                ->addArgument($di->getDefinition('manager.submission'))
                ->addArgument($di->getDefinition('site.cache'))
                ->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')])
                ->addMethodCall('setAbilityNeeded', ['edit_post'])
                ->addMethodCall('setServedContentType', [$this->getSystemName()])
                ->addMethodCall('setNoOriginalFound', [__($this->getConfigParser()->getWidgetMessage())]);

            $di->get($tag)->register();
            $this->registerJobWidget();
        }
    }

    /**
     * Alters DI container
     */
    protected function registerJobWidget(): void
    {
        $di = $this->getContainerBuilder();
        $tag = 'wp.job.' . $this->getSystemName();

        $di
            ->register($tag, ContentEditJobController::class)
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('manager.settings'))
            ->addArgument($di->getDefinition('site.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            ->addMethodCall('setServedContentType', [$this->getSystemName()]);
        $di->get($tag)->register();
    }
}
