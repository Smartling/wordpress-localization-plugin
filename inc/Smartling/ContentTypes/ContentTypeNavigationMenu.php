<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDataReadException;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
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
     * @param ContainerBuilder $di
     * @param string           $manager
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
     * Handler to register IO Wrapper
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
     * @param ProcessRelatedContentParams $params
     * @return void
     * @throws BlogNotFoundException
     * @throws SmartlingDataReadException
     */
    public function gatherRelatedContent(ProcessRelatedContentParams $params)
    {
        $accumulator = &$params->getAccumulator();
        if (ContentTypeNavigationMenuItem::WP_CONTENT_TYPE === $params->getContentType()) {
            $this->logger->debug(
                vsprintf('Searching for menuItems related to submission = \'%s\'.', [
                    $params->getSubmission()->getId(),
                ])
            );

            $ids = $this->customMenuHelper->getMenuItems(
                $params->getSubmission()->getSourceId(),
                $params->getSubmission()->getSourceBlogId()
            );

            foreach ($ids as $menuItemEntity) {

                $this->logger->debug(
                    vsprintf('Sending for translation entity = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
                        ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
                        $menuItemEntity->getPK(),
                        $params->getSubmission()->getId(),
                    ]));

                $menuItemSubmission = $this->translationHelper->tryPrepareRelatedContent(
                    ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
                    $params->getSubmission()->getSourceBlogId(),
                    $menuItemEntity->getPK(),
                    $params->getSubmission()->getTargetBlogId(),
                    $params->getSubmission()->getBatchUid(),
                    (1 === $params->getSubmission()->getIsCloned())
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
            ContentTypeWidget::WP_CONTENT_TYPE === $params->getSubmission()->getContentType()
        ) {
            $this->logger->debug(vsprintf('Searching for menu related to widget for submission = \'%s\'.', [
                    $params->getSubmission()->getId(),
                ]));
            /**
             * @var WidgetEntity $originalEntity
             */
            $originalEntity = $this->contentHelper->readSourceContent($params->getSubmission());

            $_settings = $originalEntity->getSettings();

            if (array_key_exists(self::WP_CONTENT_TYPE, $_settings)) {
                $menuId = (int)$_settings[self::WP_CONTENT_TYPE];
            } else {
                $menuId = 0;
            }
            if (0 !== $menuId) {
                $this->logger->debug(
                    vsprintf('Sending for translation menu related to widget id = \'%s\' related to submission = \'%s\'.', [
                        $originalEntity->getPK(),
                        $params->getSubmission()->getId(),
                    ])
                );

                $relatedObjectSubmission = $this->translationHelper->tryPrepareRelatedContent(
                    self::WP_CONTENT_TYPE,
                    $params->getSubmission()->getSourceBlogId(),
                    $menuId,
                    $params->getSubmission()->getTargetBlogId(),
                    $params->getSubmission()->getBatchUid(),
                    (1 === $params->getSubmission()->getIsCloned())
                );

                $newMenuId = $relatedObjectSubmission->getTargetId();

                /**
                 * @var WidgetEntity $targetContent
                 */
                $targetContent = $this->contentHelper->readTargetContent($params->getSubmission());

                $settings = $targetContent->getSettings();
                $settings[self::WP_CONTENT_TYPE] = $newMenuId;
                $targetContent->setSettings($settings);

                $this->contentHelper->writeTargetContent($params->getSubmission(), $targetContent);
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
