<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\CustomMenuContentTypeHelper;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\TranslationHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Queue\Queue;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
            ->addArgument([ContentTypeNavigationMenuItem::WP_CONTENT_TYPE]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

    }

    public function gatherRelatedContent(ProcessRelatedContentParams $params)
    {
        $accumulator = &$params->getAccumulator();
        $helper = $this->getContainerBuilder()->get('helper.customMenu');
        $contentHelper = $this->getContainerBuilder()->get('content.helper');
        $translationHelper = $this->getContainerBuilder()->get('translation.helper');

        /**
         * @var CustomMenuContentTypeHelper $helper
         * @var TranslationHelper           $translationHelper
         * @var ContentHelper               $contentHelper
         */

        if (ContentTypeNavigationMenuItem::WP_CONTENT_TYPE === $params->getContentType()) {

            $this->getContainerBuilder()->get('logger')->debug(
                vsprintf('Searching for menuItems related to submission = \'%s\'.', [
                    $params->getSubmission()->getId(),
                ])
            );

            $ids = $helper->getMenuItems(
                $params->getSubmission()->getSourceId(),
                $params->getSubmission()->getSourceBlogId()
            );

            $menuItemIds = [];

            /** @var MenuItemEntity $menuItem */
            foreach ($ids as $menuItemEntity) {

                $menuItemIds[] = $menuItemEntity->getPK();

                $this->getContainerBuilder()->get('logger')->debug(
                    vsprintf('Sending for translation entity = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
                        ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
                        $menuItemEntity->getPK(),
                        $params->getSubmission()->getId(),
                    ]));

                $menuItemSubmission = $translationHelper->tryPrepareRelatedContent(
                    ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
                    $params->getSubmission()->getSourceBlogId(),
                    $menuItemEntity->getPK(),
                    $params->getSubmission()->getTargetBlogId()
                );

                //enqueue for download menu item
                $this->getContainerBuilder()->get('queue.db')->enqueue(
                    [$menuItemSubmission->getId()],
                    Queue::QUEUE_NAME_DOWNLOAD_QUEUE
                );

                $accumulator[ContentTypeNavigationMenu::WP_CONTENT_TYPE][] = $menuItemSubmission->getTargetId();
            }
            do_action(DownloadTranslationJob::JOB_HOOK_NAME);
        }

        if (ContentTypeNavigationMenu::WP_CONTENT_TYPE === $params->getContentType() &&
            ContentTypeWidget::WP_CONTENT_TYPE === $params->getSubmission()->getContentType()
        ) {
            $this->getContainerBuilder()->get('logger')
                ->debug(vsprintf('Searching for menu related to widget for submission = \'%s\'.', [
                    $params->getSubmission()->getId(),
                ]));

            $originalEntity = $contentHelper->readSourceContent($params->getSubmission());

            $_settings = $originalEntity->getSettings();

            if (array_key_exists(ContentTypeNavigationMenu::WP_CONTENT_TYPE, $_settings)) {
                $menuId = (int)$_settings[ContentTypeNavigationMenu::WP_CONTENT_TYPE];
            } else {
                $menuId = 0;
            }
            /**
             * @var WidgetEntity $originalEntity
             */

            if (0 !== $menuId) {

                $this->getContainerBuilder()->get('logger')->debug(
                    vsprintf('Sending for translation menu related to widget id = \'%s\' related to submission = \'%s\'.', [
                        $originalEntity->getPK(),
                        $params->getSubmission()->getId(),
                    ])
                );

                $relatedObjectSubmission = $translationHelper->tryPrepareRelatedContent(
                    ContentTypeNavigationMenu::WP_CONTENT_TYPE,
                    $params->getSubmission()->getSourceBlogId(),
                    $menuId,
                    $params->getSubmission()->getTargetBlogId()
                );

                $newMenuId = $relatedObjectSubmission->getTargetId();

                /**
                 * @var WidgetEntity $targetContent
                 */
                $targetContent = $this->getContentHelper()->readTargetContent($params->getSubmission());

                $settings = $targetContent->getSettings();
                $settings[ContentTypeNavigationMenu::WP_CONTENT_TYPE] = $newMenuId;
                $targetContent->setSettings($settings);

                $this->getContentHelper()->writeTargetContent($params->getSubmission(), $targetContent);
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