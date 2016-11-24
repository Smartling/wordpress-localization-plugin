<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\EventParameters\ProcessRelatedTermParams;
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
        $descriptor = new self($di);
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
            ->register('wp.post', 'Smartling\WP\Controller\PostWidgetController')
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('entity.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            ->addMethodCall('setDetectChangesHelper', [$di->getDefinition('detect-changes.helper')]);

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
            ->addArgument(['post_tag', 'category']);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

    }

    public function registerTaxonomyRelations(ProcessRelatedTermParams $params)
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
                    $this->getContainerBuilder()->get('logger')->debug('!!!!!!' .
                                                                       var_export($params->getAccumulator(), true));
                }
            }
        }
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
        add_action(ExportedAPI::ACTION_SMARTLING_PROCESSOR_RELATED_TERM, [$this, 'registerTaxonomyRelations']);

        //$this->registerTaxonomyRelations();
        /*
            $postTypeReferencedFields = [
                'pagelink',
            ];

            $logger = $di->get('logger');
            $translationHelper = $di->get('translation.helper');
            $contentHelper = $di->get('content.helper');

            $manager = $di->get('meta-field.processor.manager');
            $handler = new \Smartling\Helpers\MetaFieldProcessor\ReferencedPageProcessor(
                $logger,
                $translationHelper,
                '^(' . implode('|', $postTypeReferencedFields) . ')$',
                'page');
            $handler->setContentHelper($contentHelper);
            $manager->registerProcessor($handler);

        */
        // TODO: Implement registerFilters() method.
    }
}