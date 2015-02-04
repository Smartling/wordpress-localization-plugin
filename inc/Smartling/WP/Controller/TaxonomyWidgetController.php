<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 23:19
 */

namespace Smartling\WP\Controller;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TaxonomyWidgetController extends WPAbstract implements WPHookInterface {
	public function register () {
		add_action( 'admin_init', array ( $this, 'init' ) );
	}

	public function init () {
		$taxonomies = get_taxonomies( array (
			'public'   => true,
			'_builtin' => true
		), 'names', 'and' );

		if ( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				add_action( "{$taxonomy}_edit_form", array ( $this, 'preView' ), 100, 1 );
				add_action( "edited_{$taxonomy}", array ( $this, 'save' ), 10, 1 );
			}
		}
	}

	public function preView ( $tag ) {
		if ( current_user_can( 'publish_posts' ) ) {
			$this->view( $tag );
		}
	}

	function save ( $term_id ) {
		if ( isset( $_POST['smartling_taxonomy_widget'] ) ) {
			//TODO save to submission
		}

		switch ( $_POST['submit'] ) {
			case 'Send to Smartling':
				//TODO sending
				break;
			case 'Download':
				//TODO download
				break;
		}
	}


}