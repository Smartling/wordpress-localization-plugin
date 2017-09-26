<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\ContentTypes\ConfigParsers\PostTypeConfigParser;
use Smartling\Extensions\TranslateLock;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\TranslationHelper;

/**
 * Class CustomPostType
 * @package Smartling\ContentTypes
 */
class CustomPostType extends PostBasedContentTypeAbstract
{
    use CustomTypeTrait;

    public function validateConfig()
    {
        $config = $this->getConfig();
        if (array_key_exists('type', $config)) {
            $this->validateType();
        }
    }

    private function validateType()
    {
        $parser = new PostTypeConfigParser($this->getConfig());

        if (!StringHelper::isNullOrEmpty($parser->getIdentifier())) {
            $this->setSystemName($parser->getIdentifier());
            $label = parent::getLabel();
            if ('unknown' === $label) {
                $label = $this->getSystemName();
            }
            $this->setLabel($label);
        }

        $this->setConfigParser($parser);
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
            ->addArgument($this->getRelatedTaxonomies());
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
            $tag = 'wp.' . static::getSystemName();
            $definition = $di
                ->register($tag, 'Smartling\WP\Controller\PostBasedWidgetControllerStd')
                ->addArgument($di->getDefinition('logger'))
                ->addArgument($di->getDefinition('multilang.proxy'))
                ->addArgument($di->getDefinition('plugin.info'))
                ->addArgument($di->getDefinition('entity.helper'))
                ->addArgument($di->getDefinition('manager.submission'))
                ->addArgument($di->getDefinition('site.cache'))
                ->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')])
                ->addMethodCall('setAbilityNeeded', ['edit_post'])
                ->addMethodCall('setServedContentType', [static::getSystemName()])
                ->addMethodCall('setNoOriginalFound', [__($this->getConfigParser()->getWidgetMessage())]);

            $di->get($tag)->register();
            $this->registerJobWidget();
        }
    }

    protected function registerJobWidget()
    {
        $di = $this->getContainerBuilder();
        $tag = 'wp.job.' . static::getSystemName();

        $definition = $di
            ->register($tag, 'Smartling\WP\Controller\ContentEditJobController')
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('entity.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            //->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')])
            //->addMethodCall('setAbilityNeeded', ['edit_post'])
            ->addMethodCall('setServedContentType', [static::getSystemName()])
            //->addMethodCall('setNoOriginalFound', [__($this->getConfigParser()->getWidgetMessage())]);
        ;
        $di->get($tag)->register();
    }

    public function registerTaxonomyRelations(ProcessRelatedContentParams $params)
    {
        if ($this->getSystemName() === $params->getSubmission()->getContentType()) {
            /**
             * @var CustomMenuContentTypeHelper $helper
             */
            $helper = $this->getContainerBuilder()->get('helper.customMenu');
            $terms = $helper->getTerms($params->getSubmission(), $params->getContentType());
            if (0 < count($terms)) {
                foreach ($terms as $element) {
                    $this->getContainerBuilder()->get('logger')
                        ->debug(vsprintf('Sending for translation term = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
                            $element->taxonomy,
                            $element->term_id,
                            $params->getSubmission()->getId(),
                        ]));

                    /**
                     * @var TranslationHelper $translationHelper
                     */
                    $translationHelper = $this->getContainerBuilder()->get('translation.helper');

                    $relatedSubmission = $translationHelper
                        ->tryPrepareRelatedContent(
                            $element->taxonomy,
                            $params->getSubmission()->getSourceBlogId(),
                            $element->term_id,
                            $params->getSubmission()->getTargetBlogId(),
                            (1 === $params->getSubmission()->getIsCloned()),
                            $params->getSubmission()->getJobId()
                        );
                    $params->getAccumulator()[$params->getContentType()][] = $relatedSubmission->getTargetId();
                    $this->getContainerBuilder()
                        ->get('logger')
                        ->debug(
                            vsprintf(
                                'Received id=%s for submission id=%s',
                                [
                                    $relatedSubmission->getTargetId(),
                                    $relatedSubmission->getId(),
                                ]
                            )
                        );
                }
            }
        }
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
        if (0 < count($this->getRelatedTaxonomies())) {
            add_action(ExportedAPI::ACTION_SMARTLING_PROCESSOR_RELATED_CONTENT, [$this, 'registerTaxonomyRelations']);
        }
    }
}