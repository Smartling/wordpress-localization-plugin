<?php

namespace Smartling\ContentTypes;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\ContentTypes\ConfigParsers\PostTypeConfigParser;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

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
            $di
                ->register($tag, 'Smartling\WP\Controller\PostBasedWidgetControllerStd')
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

        $di
            ->register($tag, 'Smartling\WP\Controller\ContentEditJobController')
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('entity.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            ->addMethodCall('setServedContentType', [static::getSystemName()]);
        $di->get($tag)->register();
    }

    /**
     * MUTATOR: Adds target ids of related taxonomies to $params->accumulator
     *
     * @param ProcessRelatedContentParams $params
     */
    public function registerTaxonomyRelations(ProcessRelatedContentParams $params)
    {
        if ($this->getSystemName() === $params->getSubmission()->getContentType()) {
            /**
             * @var CustomMenuContentTypeHelper $helper
             */
            $helper = $this->getContainerBuilder()->get('helper.customMenu');
            $contentRelationDiscoveryService = $this->getContainerBuilder()->get('service.relations-discovery');
            $translationHelper = $this->getContainerBuilder()->get('translation.helper');
            $submission = $params->getSubmission();
            $submissionManager = $this->getContainerBuilder()->get('manager.submission');

            $terms = $helper->getTerms($params->getSubmission(), $params->getContentType());
            $logger = MonologWrapper::getLogger(static::class);

            foreach ($terms as $element) {
                if (GlobalSettingsManager::isLinkTaxonomySource()) {
                    try {
                        $targetId = $this->link($logger, $element, $submission, $contentRelationDiscoveryService, $submissionManager);
                    } catch (EntityNotFoundException $e) {
                        $logger->debug($e->getMessage());
                        $targetId = $this->sendForTranslation($logger, $element, $submission, $translationHelper);
                    }
                } else {
                    $targetId = $this->sendForTranslation($logger, $element, $submission, $translationHelper);
                }

                $params->getAccumulator()[$params->getContentType()][] = $targetId;
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

    /**
     * @param LoggerInterface $logger
     * @param \WP_Term $element
     * @param SubmissionEntity $submission
     * @param TranslationHelper $translationHelper
     * @return int
     */
    private function sendForTranslation(LoggerInterface $logger, \WP_Term $element, SubmissionEntity $submission, TranslationHelper $translationHelper)
    {
        $logger->debug(vsprintf('Sending for translation term = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
            $element->taxonomy,
            $element->term_id,
            $submission->getId(),
        ]));

        return $translationHelper->tryPrepareRelatedContent(
                $element->taxonomy,
                $submission->getSourceBlogId(),
                $element->term_id,
                $submission->getTargetBlogId(),
                $submission->getBatchUid(),
                (1 === $submission->getIsCloned())
            )->getTargetId();
    }

    /**
     * @param LoggerInterface $logger
     * @param \WP_Term $element
     * @param SubmissionEntity $submission
     * @param ContentRelationsDiscoveryService $contentRelationsDiscoveryService
     * @param SubmissionManager $submissionManager
     * @return int
     */
    private function link(LoggerInterface $logger, \WP_Term $element, SubmissionEntity $submission, ContentRelationsDiscoveryService $contentRelationsDiscoveryService, SubmissionManager $submissionManager)
    {
        $logger->debug("Linking term = '{$element->taxonomy}' id = '$element->term_id' related to submission = '{$submission->getId()}'.");

        $original = $contentRelationsDiscoveryService->findOriginalSubmission($element->taxonomy, $submission->getSourceBlogId(), $element->term_id);
        if ($original === null) {
            throw new EntityNotFoundException("Unable to find original submission for {$submission->getId()}");
        }
        $translated = $submissionManager->find([
            SubmissionEntity::FIELD_CONTENT_TYPE => $element->taxonomy,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $original->getSourceBlogId(),
            SubmissionEntity::FIELD_SOURCE_ID => $original->getSourceId(),
        ]);
        if (count($translated) !== 1) {
            throw new EntityNotFoundException("Unable to find translated submission for {$submission->getId()}");
        }
        $translated = ArrayHelper::first($translated);
        if ($translated->getTargetId() === 0) {
            throw new EntityNotFoundException("Translated submission for {$submission->getId()} has no target");
        }
        return $translated->getTargetId();
    }
}
