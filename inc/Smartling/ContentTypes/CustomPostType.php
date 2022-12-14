<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\ContentTypes\ConfigParsers\PostTypeConfigParser;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\StringHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
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
    protected function registerJobWidget()
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

    /**
     * Alters $params->accumulator
     * @throws SmartlingConfigException
     */
    public function registerTaxonomyRelations(ProcessRelatedContentParams $params): void
    {
        $submission = $params->getSubmission();
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();
        if ($this->getSystemName() === $submission->getContentType()) {
            $menuHelper = $this->getContainerBuilder()->get('helper.customMenu');
            if (!$menuHelper instanceof CustomMenuContentTypeHelper) {
                throw new SmartlingConfigException(CustomMenuContentTypeHelper::class . ' expected in DI for `helper.customMenu`');
            }
            foreach ($this->getContainerBuilder()->get('helper.customMenu')->getTerms($submission, $params->getContentType()) as $element) {
                $id = $element->term_id;
                $submissionManager = $this->getContainerBuilder()->get('manager.submission');
                if (!$submissionManager instanceof SubmissionManager) {
                    throw new SmartlingConfigException(SubmissionManager::class . ' expected in DI for `manager.submission`');
                }
                $relatedSubmission = $submissionManager->findOne([
                    SubmissionEntity::FIELD_CONTENT_TYPE => $params->getContentType(),
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                    SubmissionEntity::FIELD_SOURCE_ID => $id,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                ]);
                if ($relatedSubmission !== null) {
                    $params->getAccumulator()[$params->getContentType()][] = $relatedSubmission->getTargetId();
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
