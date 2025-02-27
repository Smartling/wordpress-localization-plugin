<?php

namespace Smartling\Helpers;

use Smartling\ContentTypes\ContentTypeNavigationMenu;
use Smartling\ContentTypes\ContentTypeNavigationMenuItem;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;

class CustomMenuContentTypeHelper
{
    use LoggerSafeTrait;

    /**
     * Wordpress meta_key name for nav_menu_item content type.
     */
    public const string META_KEY_MENU_ITEM_PARENT = '_menu_item_menu_item_parent';

    private ContentEntitiesIOFactory $contentIoFactory;
    private SiteHelper $siteHelper;

    public function __construct(ContentEntitiesIOFactory $contentIoFactory, SiteHelper $siteHelper)
    {
        $this->contentIoFactory = $contentIoFactory;
        $this->siteHelper = $siteHelper;
    }

    /**
     * @param int[] $items
     * @throws BlogNotFoundException
     */
    public function assignMenuItemsToMenu(int $menuId, int $blogId, array $items): void
    {
        $needBlogChange = $blogId !== $this->siteHelper->getCurrentBlogId();
        if ($needBlogChange) {
            $this->siteHelper->switchBlogId($blogId);
        }

        foreach ($items as $item) {
            wp_set_object_terms($item, [$menuId], ContentTypeNavigationMenu::WP_CONTENT_TYPE);
        }

        if ($needBlogChange) {
            $this->siteHelper->restoreBlogId();
        }
    }

    /**
     * @return PostEntityStd[]
     * @throws BlogNotFoundException
     */
    public function getMenuItems(int $menuId, int $blogId): array
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

        $needBlogSwitch = $this->siteHelper->getCurrentBlogId() !== $blogId;

        $ids = [];

        if ($needBlogSwitch) {
            $this->siteHelper->switchBlogId($blogId);
        }

        $items = wp_get_nav_menu_items($menuId, $options);

        $mapper = $this->contentIoFactory->getMapper(ContentTypeNavigationMenuItem::WP_CONTENT_TYPE);
        foreach ($items as $item) {
            $m = clone $mapper;
            $ids[] = $m->get((int)$item->ID);
        }

        if ($needBlogSwitch) {
            $this->siteHelper->restoreBlogId();
        }

        return $ids;
    }

    /**
     * @return \WP_Term[]
     */
    public function getTerms(SubmissionEntity $submission, string $taxonomy): array
    {
        $needBlogSwitch = $submission->getSourceBlogId() !== $this->siteHelper->getCurrentBlogId();
        $terms = [];

        try {
            if ($needBlogSwitch) {
                $this->siteHelper->switchBlogId($submission->getSourceBlogId());
            }

            $terms = wp_get_object_terms($submission->getSourceId(), $taxonomy);

            if ($needBlogSwitch) {
                $this->siteHelper->restoreBlogId();
            }
        } catch (BlogNotFoundException $e) {
            $this->getLogger()->warning(vsprintf('Cannot get terms in missing blog.', []));
        }

        return is_array($terms) ? $terms : [];
    }
}
