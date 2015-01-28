<?php

namespace Smartling\Helpers;

use Smartling\Exception\SmartlingDirectRunRuntimeException;

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
	private static $_reverse_map = array (
		'post'     => self::CONTENT_TYPE_POST,
		'page'     => self::CONTENT_TYPE_PAGE,
		'category' => self::CONTENT_TYPE_CATEGORY
	);

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
		return array (
			self::CONTENT_TYPE_POST     => __( 'Post' ),
			self::CONTENT_TYPE_PAGE     => __( 'Page' ),
			self::CONTENT_TYPE_CATEGORY => __( 'Category' ),
		);
	}


}