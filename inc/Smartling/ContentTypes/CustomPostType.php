<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\ContentTypes\ConfigParsers\PostTypeConfigParser;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\StringHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\WP\Controller\PostBasedWidgetControllerStd;
use Smartling\WP\Controller\ContentEditJobController;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;

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
     * @return void
     */
    public function registerIOWrapper()
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
     * @return void
     */
    public function registerWidgetHandler()
    {
        if ($this->getConfigParser()->hasWidget()) {
            $di = $this->getContainerBuilder();
            $tag = 'wp.' . $this->getSystemName();
            $di
                ->register($tag, PostBasedWidgetControllerStd::class)
                ->addArgument($di->getDefinition('api.wrapper.with.retries'))
                ->addArgument($di->getDefinition('multilang.proxy'))
                ->addArgument($di->getDefinition('plugin.info'))
                ->addArgument($di->getDefinition('entity.helper'))
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
    protected function registerJobWidget()
    {
        $di = $this->getContainerBuilder();
        $tag = 'wp.job.' . $this->getSystemName();

        $di
            ->register($tag, ContentEditJobController::class)
            ->addArgument($di->getDefinition('multilang.proxy'))
            ->addArgument($di->getDefinition('plugin.info'))
            ->addArgument($di->getDefinition('entity.helper'))
            ->addArgument($di->getDefinition('manager.submission'))
            ->addArgument($di->getDefinition('site.cache'))
            ->addMethodCall('setServedContentType', [$this->getSystemName()]);
        $di->get($tag)->register();
    }

    /**
     * Alters $params->accumulator
     *
     * @param ProcessRelatedContentParams $params
     * @return void
     * @throws SmartlingDataReadException
     */
    public function registerTaxonomyRelations(ProcessRelatedContentParams $params)
    {
        $submission = $params->getSubmission();
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();
        if ($this->getSystemName() === $submission->getContentType()) {
            $logger = MonologWrapper::getLogger(static::class);
            foreach ($this->getContainerBuilder()->get('helper.customMenu')->getTerms($submission, $params->getContentType()) as $element) {
                $contentType = $element->taxonomy;
                $id = $element->term_id;
                $logger->debug("Sending for translation term = '{$contentType}' id = '$id' related to submission = '{$submission->getId()}'.");
                $translationHelper = $this->getContainerBuilder()->get('translation.helper');
                if ($translationHelper->isRelatedSubmissionCreationNeeded($contentType, $sourceBlogId, $id, $targetBlogId)) {
                    $relatedSubmission = $translationHelper->tryPrepareRelatedContent(
                        $contentType,
                        $sourceBlogId,
                        $id,
                        $targetBlogId,
                        $submission->getJobInfoWithBatchUid(),
                        (1 === $submission->getIsCloned())
                    );
                    $params->getAccumulator()[$params->getContentType()][] = $relatedSubmission->getTargetId();
                    $logger->debug("Received id={$relatedSubmission->getTargetId()} for submission id={$relatedSubmission->getId()}");
                } else {
                    $logger->debug("Skipped sending term $id for translation due to manual relations handling");
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
