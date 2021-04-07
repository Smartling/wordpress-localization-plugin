<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\JobInfo;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Queue\Queue;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;

/**
 * Class ContentTypeNavigationMenu
 * @package Smartling\ContentTypes
 */
class ContentTypeNavigationMenu extends TermBasedContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'nav_menu';

    private $contentHelper;
    private $customMenuHelper;
    private $logger;
    private $translationHelper;

    /**
     * ContentTypeNavigationMenu constructor.
     *
     * @param ContainerBuilder $di
     */
    public function __construct(ContainerBuilder $di)
    {
        parent::__construct($di);
        $this->contentHelper = $di->get('content.helper');
        $this->customMenuHelper = $di->get('helper.customMenu');
        $this->logger = MonologWrapper::getLogger(static::class);
        $this->translationHelper = $this->getContainerBuilder()->get('translation.helper');

        $this->registerIOWrapper();
        $this->registerWidgetHandler();
        $this->registerFilters();
    }

    /**
     * Alters DI container
     * @param ContainerBuilder $di
     * @param string $manager
     */
    public static function register(ContainerBuilder $di, $manager = 'content-type-descriptor-manager')
    {
        $descriptor = new static($di);
        $mgr = $di->get($manager);
        /**
         * @var ContentTypeManager $mgr
         */
        $mgr->addDescriptor($descriptor);
    }

    /**
     * Handler to register Widget (Edit Screen)
     * @return void
     */
    public function registerWidgetHandler()
    {

    }

    /**
     * Handler to register IO Wrapper. Alters DI container.
     * @return void
     */
    public function registerIOWrapper()
    {
        $di = $this->getContainerBuilder();
        $wrapperId = 'wrapper.entity.' . $this->getSystemName();
        $definition = $di->register($wrapperId, TaxonomyEntityStd::class );
        $definition
            ->addArgument($this->getSystemName())
            ->addArgument([ContentTypeNavigationMenuItem::WP_CONTENT_TYPE]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

    }

    /**
     * MUTATOR: alters $params->accumulator
     *
     * @throws BlogNotFoundException
     * @throws SmartlingDataReadException
     */
    public function gatherRelatedContent(ProcessRelatedContentParams $params): void
    {
        $accumulator = &$params->getAccumulator();
        $submission = $params->getSubmission();
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();

        if (ContentTypeNavigationMenuItem::WP_CONTENT_TYPE === $params->getContentType()) {
            $this->logger->debug(
                vsprintf('Searching for menuItems related to submission = \'%s\'.', [
                    $submission->getId(),
                ])
            );

            foreach ($this->customMenuHelper->getMenuItems($submission->getSourceId(), $sourceBlogId) as $menuItemEntity) {
                $this->logger->debug(
                    vsprintf('Sending for translation entity = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
                        ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
                        $menuItemEntity->getPK(),
                        $params->getSubmission()->getId(),
                    ]));

                $menuItemSubmission = $this->translationHelper->tryPrepareRelatedContent(
                    ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
                    $sourceBlogId,
                    $menuItemEntity->getPK(),
                    $targetBlogId,
                    $submission->getJobInfo(),
                    (1 === $submission->getIsCloned())
                );

                //enqueue for download menu item
                $this->getContainerBuilder()->get('queue.db')->enqueue(
                    [$menuItemSubmission->getId()],
                    Queue::QUEUE_NAME_DOWNLOAD_QUEUE
                );

                $accumulator[self::WP_CONTENT_TYPE][] = $menuItemSubmission->getTargetId();
            }
            do_action(DownloadTranslationJob::JOB_HOOK_NAME);
        }

        if (self::WP_CONTENT_TYPE === $params->getContentType() &&
            ContentTypeWidget::WP_CONTENT_TYPE === $submission->getContentType()
        ) {
            $this->logger->debug("Searching for menu related to widget for submission = '{$submission->getId()}'.");

            $originalEntity = $this->contentHelper->readSourceContent($submission);
            if (!$originalEntity instanceof WidgetEntity) {
                throw new \RuntimeException('Original entity expected to be instance of ' . WidgetEntity::class);
            }

            $_settings = $originalEntity->getSettings();

            $menuId = 0;
            if (array_key_exists(self::WP_CONTENT_TYPE, $_settings)) {
                $menuId = (int)$_settings[self::WP_CONTENT_TYPE];
            }
            if (0 !== $menuId) {
                $this->logger->debug("Processing menu related to widget id = '{$originalEntity->getPK()}' related to submission = '{$submission->getId()}'.");

                if ($this->translationHelper->isRelatedSubmissionCreationNeeded(self::WP_CONTENT_TYPE, $sourceBlogId, $menuId, $targetBlogId)) {
                    $this->logger->debug("Sending menu id={$originalEntity->getPK()} for translation");
                    $relatedObjectSubmission = $this->translationHelper->tryPrepareRelatedContent(
                        self::WP_CONTENT_TYPE,
                        $sourceBlogId,
                        $menuId,
                        $targetBlogId,
                        $submission->getJobInfo(),
                        (1 === $submission->getIsCloned())
                    );

                    $newMenuId = $relatedObjectSubmission->getTargetId();

                    $targetContent = $this->contentHelper->readTargetContent($submission);
                    if (!$targetContent instanceof WidgetEntity) {
                        throw new \RuntimeException('Target entity expected to be instance of ' . WidgetEntity::class);
                    }

                    $settings = $targetContent->getSettings();
                    $settings[self::WP_CONTENT_TYPE] = $newMenuId;
                    $targetContent->setSettings($settings);

                    $this->contentHelper->writeTargetContent($submission, $targetContent);
                } else {
                    $this->logger->debug("Skip sending menu id={$originalEntity->getPK()} for translation");
                }
            }
        }
    }

    /**
     * @return void
     */
    public function registerFilters()
    {
        add_action(ExportedAPI::ACTION_SMARTLING_PROCESSOR_RELATED_CONTENT, [$this, 'gatherRelatedContent']);
    }
}
