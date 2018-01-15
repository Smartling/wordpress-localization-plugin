<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\ContentTypes\ContentTypeNavigationMenu;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Exception\BlogNotFoundException;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class CustomMenuContentTypeHelper
 * @package Smartling\Helpers
 */
class CustomMenuContentTypeHelper
{

    /**
     * Wordpress meta_key name for nav_menu_item content type.
     */
    const META_KEY_MENU_ITEM_PARENT = '_menu_item_menu_item_parent';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ContentEntitiesIOFactory
     */
    private $contentIoFactory;

    /**
     * @var SiteHelper
     */
    private $siteHelper;

    public function __construct() {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return ContentEntitiesIOFactory
     */
    public function getContentIoFactory()
    {
        return $this->contentIoFactory;
    }

    /**
     * @param ContentEntitiesIOFactory $contentIoFactory
     */
    public function setContentIoFactory($contentIoFactory)
    {
        $this->contentIoFactory = $contentIoFactory;
    }

    /**
     * @return SiteHelper
     */
    public function getSiteHelper()
    {
        return $this->siteHelper;
    }

    /**
     * @param SiteHelper $siteHelper
     */
    public function setSiteHelper($siteHelper)
    {
        $this->siteHelper = $siteHelper;
    }

    /**
     * @param int   $menuId
     * @param int   $blogId
     * @param int[] $items
     *
     * @throws BlogNotFoundException
     */
    public function assignMenuItemsToMenu($menuId, $blogId, $items)
    {
        $needBlogChange = $blogId !== $this->getSiteHelper()->getCurrentBlogId();
        try {
            if ($needBlogChange) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }

            foreach ($items as $item) {
                wp_set_object_terms($item, [(int)$menuId], ContentTypeNavigationMenu::WP_CONTENT_TYPE);
            }

            if ($needBlogChange) {
                $this->getSiteHelper()->restoreBlogId();
            }
        } catch (BlogNotFoundException $e) {
            throw $e;
        }
    }

    /**
     * @param int $menuId
     * @param int $blogId
     *
     * @return PostEntityStd[]
     * @throws BlogNotFoundException
     */
    public function getMenuItems($menuId, $blogId)
    {
        $options = [
            'order'                  => 'ASC',
            'orderby'                => 'menu_order',
            'post_type'              => ContentTypeNavigationMenuItem::WP_CONTENT_TYPE,
            'post_status'            => 'publish',
            'output'                 => ARRAY_A,
            'output_key'             => 'menu_order',
            'nopaging'               => true,
            'update_post_term_cache' => false,
        ];

        $needBlogSwitch = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;

        $ids = [];

        try {
            if ($needBlogSwitch) {
                $this->getSiteHelper()->switchBlogId($blogId);
            }

            $items = wp_get_nav_menu_items($menuId, $options);

            $mapper = $this->getContentIoFactory()->getMapper(ContentTypeNavigationMenuItem::WP_CONTENT_TYPE);
            foreach ($items as $item) {
                $m = clone $mapper;
                $ids[] = $m->get((int)$item->ID);
            }

            if ($needBlogSwitch) {
                $this->getSiteHelper()->restoreBlogId();
            }
        } catch (BlogNotFoundException $e) {
            throw $e;
        }

        return $ids;
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $taxonomy
     *
     * @return array
     */
    public function getTerms($submission, $taxonomy)
    {
        $needBlogSwitch = $submission->getSourceBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

        try {
            if ($needBlogSwitch) {
                $this->getSiteHelper()->switchBlogId($submission->getSourceBlogId());
            }

            $terms = wp_get_object_terms($submission->getSourceId(), $taxonomy);

            if ($needBlogSwitch) {
                $this->getSiteHelper()->restoreBlogId();
            }
        } catch (BlogNotFoundException $e) {
            $this->getLogger()->warning(vsprintf('Cannot get terms in missing blog.', []));
        }

        return null !== $terms && is_array($terms) ? $terms : [];
    }


}