<?php

use Smartling\Helpers\DateTimeHelper;
use Smartling\Settings\SettingsManager;

/**
 * Constants
 */


defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );

/**
 * Functions
 */

if ( ! function_exists( '__' ) ) {
	function __ ( $text, $scope = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id () {
		return 1;
	}
}

if ( ! function_exists( 'get_current_site' ) ) {
	function get_current_site () {
		return (object) array ( 'id' => 1 );
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user () {
		return (object) array ( 'user_login' => 1 );
	}
}

if ( ! function_exists( 'wp_get_sites' ) ) {
	function wp_get_sites () {
		return array (
			array (
				'site_id' => 1,
				'blog_id' => 1
			),
			array (
				'site_id' => 1,
				'blog_id' => 2
			),
			array (
				'site_id' => 1,
				'blog_id' => 3
			),
			array (
				'site_id' => 1,
				'blog_id' => 4
			),
		);
	}
}

if ( ! function_exists( 'ms_is_switched' ) ) {
	function ms_is_switched () {
		return true;
	}
}

if ( ! function_exists( 'restore_current_blog' ) ) {
	function restore_current_blog () {
		return true;
	}
}

if ( ! function_exists( 'switch_to_blog' ) ) {
	function switch_to_blog ( $blogId ) {
		return true;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option ( $key, $default = null, $useCache = true ) {
		switch ( $key ) {
			case SettingsManager::SMARTLING_ACCOUNT_INFO: {
				return array (
					'apiUrl'        => 'https://capi.smartling.com/v1',
					'projectId'     => 'a',
					'key'           => 'b',
					'retrievalType' => 'pseudo',
					'callBackUrl'   => '',
					'autoAuthorize' => true
				);
				break;
			}
			case SettingsManager::SMARTLING_LOCALES: {
				return array (
					'defaultLocale' => 'en-US',
					'targetLocales' => array (
						array (
							'locale'  => 'ru-Ru',
							'target'  => true,
							'enabled' => true,
							'blog'    => 2
						)
					),
					'defaultBlog'   => 1

				);
				break;
			}
		}

	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error ( $something ) {
		return false;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo ( $part ) {
		return $part;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post ( $id, $returnError ) {

		$date = DateTimeHelper::nowAsString();

		$type = $id < 10 ? 'post' : 'page';

		$post = array (
			'ID'                    => $id,
			'post_author'           => 1,
			'post_date'             => $date,
			'post_date_gmt'         => $date,
			'post_content'          => 'Test content',
			'post_title'            => 'Here goes the title',
			'post_excerpt'          => '',
			'post_status'           => 'published',
			'comment_status'        => 'open',
			'ping_status'           => '',
			'post_password'         => '',
			'post_name'             => 'Here goes the title',
			'to_ping'               => '',
			'pinged'                => '',
			'post_modified'         => $date,
			'post_modified_gmt'     => $date,
			'post_content_filtered' => '',
			'post_parent'           => 0,
			'guid'                  => '/here-goes-the-title',
			'menu_order'            => 0,
			'post_type'             => $type,
			'post_mime_type'        => 'post',
			'comment_count'         => 0,
		);

		return $post;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post ( array $fields, $returnError ) {
		return $fields['ID'] ? : 2;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta ( $postId ) {
		return array (
			'meta1' => array ( 'value1' ),
			'meta2' => array ( 'value2' ),
			'meta3' => array ( 'value3' ),
		);
	}
}

if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists ( $meta_type, $object_id, $meta_key ) {
		return rand( 0, 9 ) >= 5;
	}
}

if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta ( $post_id, $meta_key, $meta_value, $unique = false ) {
		return true;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta ( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		return true;
	}
}

if ( ! function_exists( 'wp_update_term' ) ) {
	function wp_update_term ( $id, $type, $args ) {
		return array_merge( $args, array ( 'term_id' => $id ) );
	}
}
if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term ( $name, $type, $args ) {
		return array_merge( $args, array ( 'term_id' => 2 ) );
	}
}
if ( ! function_exists( 'get_term' ) ) {
	function get_term ( $id, $taxonomy, $outputFormat ) {
		$category = array (
			'term_id'          => $id,
			'name'             => 'Fake Name',
			'slug'             => 'fake-name',
			'term_group'       => 0,
			'term_taxonomy_id' => 0,
			'taxonomy'         => $taxonomy,
			'description'      => '',
			'parent'           => 0,
			'count'            => 0
		);


		return $category;
	}
}
