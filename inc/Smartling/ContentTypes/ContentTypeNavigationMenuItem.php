<?php

namespace Smartling\ContentTypes;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ContentTypeNavigationMenuItem
 * @package Smartling\ContentTypes
 */
class ContentTypeNavigationMenuItem extends PostBasedContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'nav_menu_item';

    public function getSystemName()
    {
        return self::WP_CONTENT_TYPE;
    }

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
        $definition = $di->register($wrapperId, 'Smartling\DbAl\WordpressContentEntities\PostEntityStd');
        $definition
            ->addArgument($di->getDefinition('logger'))
            ->addArgument($this->getSystemName())
            ->addArgument([]);

        $di->get('factory.contentIO')->registerHandler($this->getSystemName(), $di->get($wrapperId));

    }

    public function getVisibility()
    {
        return [
            'submissionBoard' => true,
            'bulkSubmit'      => false,
        ];
    }

    public function gatherRelatedContent(ProcessRelatedContentParams $params)
    {
        $helper = $this->getContainerBuilder()->get('helper.customMenu');
        $contentHelper = $this->getContainerBuilder()->get('content.helper');

        /**
         * @var TranslationHelper $translationHelper
         * @var ContentHelper $contentHelper
         */

        $translationHelper = $this->getContainerBuilder()->get('translation.helper');

        if (self::WP_CONTENT_TYPE === $params->getContentType()) {
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
                        self::WP_CONTENT_TYPE,
                        $menuItemEntity->getPK(),
                        $params->getSubmission()->getId(),
                    ]));

                $menuItemSubmission = $translationHelper->tryPrepareRelatedContent(
                    self::WP_CONTENT_TYPE,
                    $params->getSubmission()->getSourceBlogId(),
                    $menuItemEntity->getPK(),
                    $params->getSubmission()->getTargetBlogId()
                );

                $originalMenuItemMeta = $contentHelper->readSourceMetadata($menuItemSubmission);
                $originalMenuItemMeta = ArrayHelper::simplifyArray($originalMenuItemMeta);
                if (in_array($originalMenuItemMeta['_menu_item_type'], ['taxonomy', 'post_type'])) {
                    $originalRelatedObjectId = (int)$originalMenuItemMeta['_menu_item_object_id'];
                    $relatedContentType = $originalMenuItemMeta['_menu_item_object'];
                    $this->getContainerBuilder()->get('logger')->debug(
                        vsprintf(
                            'Sending for translation object = \'%s\' id = \'%s\' related to \'%s\' related to submission = \'%s\'.',
                            [
                                $relatedContentType,
                                $originalRelatedObjectId,
                                self::WP_CONTENT_TYPE,
                                $menuItemSubmission->getId(),
                            ]
                        )
                    );
                    $relatedObjectSubmission = $translationHelper->tryPrepareRelatedContent(
                        $relatedContentType,
                        $params->getSubmission()->getSourceBlogId(),
                        $originalRelatedObjectId,
                        $params->getSubmission()->getTargetBlogId()
                    );
                    $relatedObjectId = $relatedObjectSubmission->getTargetId();
                    $originalMenuItemMeta['_menu_item_object_id'] = $relatedObjectId;
                    $contentHelper->writeTargetMetadata($menuItemSubmission, $originalMenuItemMeta);
                }
                $accumulator[ContentTypeNavigationMenu::WP_CONTENT_TYPE][] = $menuItemSubmission->getTargetId();
            }
            $helper->rebuildMenuHierarchy(
                $params->getSubmission()->getSourceBlogId(),
                $params->getSubmission()->getTargetBlogId(),
                $menuItemIds
            );
        }

        if (ContentTypeNavigationMenuItem::WP_CONTENT_TYPE === $params->getContentType() &&
            ContentTypeWidget::WP_CONTENT_TYPE === $params->getSubmission()->getContentType())
        {
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