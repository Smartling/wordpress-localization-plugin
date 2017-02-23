<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\TranslationHelper;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypePost
 * @package Smartling\ContentTypes
 */
class ContentTypePost extends PostBasedContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'post';

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
     * Handler to register Widget (Edit Screen)
     * @return void
     */
    public function registerWidgetHandler()
    {
        $di = $this->getContainerBuilder();

        $definition = $di
            ->register('wp.post', 'Smartling\WP\Controller\PostBasedWidgetControllerStd')
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('entity.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            ->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')])
            ->addMethodCall('setAbilityNeeded', ['edit_post'])
            ->addMethodCall('setServedContentType', [$this->getSystemName()])
            ->addMethodCall('setNoOriginalFound', [__('No original post found')]);

        $di->getDefinition('manager.register')->addMethodCall('addService', [$definition]);
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
            // Post has 2 related types: tags and category
            ->addArgument([ContentTypePostTag::WP_CONTENT_TYPE, ContentTypeCategory::WP_CONTENT_TYPE]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

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
                            $params->getSubmission()->getTargetBlogId()
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

        $di = $this->getContainerBuilder();
        $wrapperId = 'referenced-content.featured_image';
        $definition = $di->register($wrapperId, 'Smartling\Helpers\MetaFieldProcessor\ReferencedFileFieldProcessor');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('translation.helper'))
            ->addArgument('_thumbnail_id');

        $di->get('meta-field.processor.manager')->registerProcessor($di->get($wrapperId));

        add_action(ExportedAPI::ACTION_SMARTLING_PROCESSOR_RELATED_CONTENT, [$this, 'registerTaxonomyRelations']);
    }
}
