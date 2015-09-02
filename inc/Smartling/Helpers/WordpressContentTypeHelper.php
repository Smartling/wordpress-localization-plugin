<?php

namespace Smartling\Helpers;

use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingNotSupportedContentException;

/**
 * Class WordpressContentTypeHelper
 *
 * @package Smartling\Helpers
 */
class WordpressContentTypeHelper {
	/**
	 * Wordpress post type identity
	 */
	const CONTENT_TYPE_POST = 'post';

	/**
	 * Wordpress page type identity
	 */
	const CONTENT_TYPE_PAGE = 'page';

	/**
	 * Wordpress category type identity
	 */
	const CONTENT_TYPE_CATEGORY = 'category';

	/**
	 * Wordpress category type identity
	 */
	const CONTENT_TYPE_POST_TAG = 'post_tag';

	/**
	 * Wordpress theme menu
	 */
	const CONTENT_TYPE_NAV_MENU = 'nav_menu';

	/**
	 * Wordpress Navigation menu item
	 */
	const CONTENT_TYPE_NAV_MENU_ITEM = 'nav_menu_item';

	/**
	 * 'term' based content type
	 */
	const CONTENT_TYPE_TERM_POLICY_TYPE = 'policy_types';
	/**
	 * 'post' based content type
	 */
	const CONTENT_TYPE_POST_POLICY = 'policy';

	/**
	 * 'post' based content type
	 */
	const CONTENT_TYPE_POST_PARTNER = 'partner';

	/**
	 * 'post' based content type
	 */
	const CONTENT_TYPE_POST_TESTIMONIAL = 'testimonial';

	/**
	 * Checks if Wordpress i10n function __ is registered
	 * if not - throws an SmartlingDirectRunRuntimeException exception
	 *
	 * @throws SmartlingDirectRunRuntimeException
	 */
	private static function checkRuntimeState () {
		if ( ! function_exists( '__' ) ) {
			$message = 'I10n Wordpress function not available on direct execution.';
			throw new SmartlingDirectRunRuntimeException( $message );
		}
	}

	/**
	 * Reverse map of Wordpress types to constants
	 *
	 * @var array
	 */
	private static $_reverse_map = [
		'post'          => self::CONTENT_TYPE_POST,
		'page'          => self::CONTENT_TYPE_PAGE,
		'category'      => self::CONTENT_TYPE_CATEGORY,
		'post_tag'      => self::CONTENT_TYPE_POST_TAG,
		'policy'        => self::CONTENT_TYPE_POST_POLICY,
		'partner'       => self::CONTENT_TYPE_POST_PARTNER,
		'testimonial'   => self::CONTENT_TYPE_POST_TESTIMONIAL,
		'nav_menu'      => self::CONTENT_TYPE_NAV_MENU,
		'nav_menu_item' => self::CONTENT_TYPE_NAV_MENU_ITEM,
	];

	/**
	 * @return array
	 * @throws SmartlingDirectRunRuntimeException
	 */
	public static function getReverseMap () {
		self::checkRuntimeState();

		return self::$_reverse_map;
	}

	/**
	 * @return array
	 * @throws SmartlingDirectRunRuntimeException
	 */
	public static function getLabelMap () {
		self::checkRuntimeState();

		// has to be hardcoded because i10n parser must see direct calls of __(CONSTANT STRING)
		return [
			self::CONTENT_TYPE_POST_POLICY      => __( 'Policy' ),
			self::CONTENT_TYPE_POST_PARTNER     => __( 'Partner' ),
			self::CONTENT_TYPE_POST_TESTIMONIAL => __( 'Testimonial' ),
			self::CONTENT_TYPE_POST             => __( 'Post' ),
			self::CONTENT_TYPE_PAGE             => __( 'Page' ),
			self::CONTENT_TYPE_CATEGORY         => __( 'Category' ),
			self::CONTENT_TYPE_POST_TAG         => __( 'Tag' ),
			self::CONTENT_TYPE_NAV_MENU         => __( 'Navigation Menu' ),
			self::CONTENT_TYPE_NAV_MENU_ITEM    => __( 'Navigation Menu Item' ),
		];
	}

	/**
	 * @return array
	 */
	public static function getSupportedTaxonomyTypes () {
		return [
			self::CONTENT_TYPE_CATEGORY,
			self::CONTENT_TYPE_POST_TAG,
			self::CONTENT_TYPE_NAV_MENU,
		];
	}

	/**
	 * @param $contentType
	 *
	 * @return string
	 * @throws SmartlingNotSupportedContentException
	 */
	public static function getLocalizedContentType ( $contentType ) {
		$map = self::getLabelMap();
		if ( array_key_exists( $contentType, $map ) ) {
			return $map[ $contentType ];
		} else {
			throw new SmartlingNotSupportedContentException( vsprintf( 'Content-type \'%s\' is not supported yet.',
				[ $contentType ] ) );
		}
	}
}