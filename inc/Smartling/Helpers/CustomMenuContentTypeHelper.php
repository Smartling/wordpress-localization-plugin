<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Processors\ContentEntitiesIOFactory;

/**
 * Class CustomMenuContentTypeHelper
 *
 * @package Smartling\Helpers
 */
class CustomMenuContentTypeHelper {

	/**
	 * @var ContentEntitiesIOFactory
	 */
	private $contentIoFactory;

	/**
	 * @return ContentEntitiesIOFactory
	 */
	public function getContentIoFactory () {
		return $this->contentIoFactory;
	}

	/**
	 * @param ContentEntitiesIOFactory $contentIoFactory
	 */
	public function setContentIoFactory ( $contentIoFactory ) {
		$this->contentIoFactory = $contentIoFactory;
	}

	/**
	 * @var SiteHelper
	 */
	private $siteHelper;

	/**
	 * @return SiteHelper
	 */
	public function getSiteHelper () {
		return $this->siteHelper;
	}

	/**
	 * @param SiteHelper $siteHelper
	 */
	public function setSiteHelper ( $siteHelper ) {
		$this->siteHelper = $siteHelper;
	}

	/**
	 * @param int   $menuId
	 * @param int   $blogId
	 * @param int[] $items
	 */
	public function assignMenuItemsToMenu ( $menuId, $blogId, $items ) {
		$needBlogChange = $blogId !== $this->getSiteHelper()->getCurrentBlogId();
		try {
			if ( $needBlogChange ) {
				$this->getSiteHelper()->switchBlogId( $blogId );
			}

			foreach ( $items as $item ) {
				wp_set_object_terms( $item, [ (int) $menuId ], WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU );
			}

			if ( $needBlogChange ) {
				$this->getSiteHelper()->restoreBlogId();
			}
		} catch ( BlogNotFoundException$e ) {
		}
	}

	/**
	 * @param int $menuId
	 * @param int $blogId
	 *
	 * @return MenuItemEntity[]
	 */
	public function getMenuItems ( $menuId, $blogId ) {
		$options = [
			'order'                  => 'ASC',
			'orderby'                => 'menu_order',
			'post_type'              => WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
			'post_status'            => 'publish',
			'output'                 => ARRAY_A,
			'output_key'             => 'menu_order',
			'nopaging'               => true,
			'update_post_term_cache' => false,
		];

		$needBlogSwitch = $this->getSiteHelper()->getCurrentBlogId() !== $blogId;

		$ids = [ ];

		try {
			if ( $needBlogSwitch ) {
				$this->getSiteHelper()->switchBlogId( $blogId );
			}

			$items = wp_get_nav_menu_items( $menuId, $options );


			$mapper = $this
				->getContentIoFactory()
				->getMapper( WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM );
			foreach ( $items as $item ) {
				$m     = clone $mapper;
				$ids[] = $m->get( (int) $item->ID );;
			}

			if ( $needBlogSwitch ) {
				$this->getSiteHelper()->restoreBlogId();
			}
		} catch ( BlogNotFoundException $e ) {
		}

		return $ids;
	}

	/**
	 * @param SubmissionEntity $submission
	 * @param string           $taxonomy
	 *
	 * @return array
	 */
	public function getTerms ( $submission, $taxonomy ) {
		$needBlogSwitch = $submission->getSourceBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

		try {
			if ( $needBlogSwitch ) {
				$this->getSiteHelper()->switchBlogId( $submission->getSourceBlogId() );
			}

			$terms = wp_get_object_terms( $submission->getSourceId(), $taxonomy );

			if ( $needBlogSwitch ) {
				$this->getSiteHelper()->restoreBlogId();
			}
		} catch ( BlogNotFoundException $e ) {
			Bootstrap::getLogger()->warning(
				vsprintf('Cannot get terms in missing blog.',[])
			);
		}

		return isset( $terms ) && is_array( $terms ) ? $terms : [ ];
	}


}