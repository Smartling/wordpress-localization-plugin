<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 22:47
 */

namespace Smartling\WP\Controller;


class PostWidgetController extends WPAbstract implements WPHookInterface {
	const WIDGET_NAME = 'smartling_connector_post_widget';
	const WIDGET_DATA_NAME = 'smartling_post_widget_data';

	public function register () {
		add_action( 'add_meta_boxes', array ( $this, 'box' ) );
		add_action( 'save_post', array ( $this, 'save' ) );
	}

	public function box ( $post_type ) {
		$post_types = array ( 'post' );
		if ( in_array( $post_type, $post_types ) ) {
			add_meta_box(
				self::WIDGET_NAME,
				'Smartling Post Widget',
				array ( $this, 'preView' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public function preView ( $post ) {
		wp_nonce_field( self::WIDGET_NAME, 'smartling_connector_nonce' );
		if ( $post->post_content
		     && $post->post_title
		) {
			//TODO add get post info
			$this->view( $post );
		} else {
			echo '<p>Need to save the post</p>';
		}
	}

	public function save ( $post_id ) {
		if ( ! isset( $_POST['smartling_connector_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['smartling_connector_nonce'];

		if ( ! wp_verify_nonce( $nonce, self::WIDGET_NAME ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
		}

		$data = $_POST['smartling_post_widget'];
		update_post_meta( $post_id, 'smartling_post_widget_data', $data );
		//TODO save to submission

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