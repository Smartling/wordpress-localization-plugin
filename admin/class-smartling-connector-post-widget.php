<?php

/**
 * The dashboard-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/admin
 */

/**
 * The dashboard-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the dashboard-specific stylesheet and JavaScript.
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/admin
 * @author     Your Name <email@example.com>
 */

class Smartling_Connector_Post_Widget
{
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	
	function __construct($plugin_name, $version) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}


	public function add_meta_box( $post_type ) {

			//limit meta box to certain post types
            $post_types = array('post'); 

            if ( in_array( $post_type, $post_types )) {

				add_meta_box(
					'smartling_connector_post_widget',
					'Smartling Post Widget',
					array( $this, 'render_meta_box_content' ),
					$post_type,
					'side',
					'high'
				);
            }
	}


	public function render_meta_box_content( $post ) {
	
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'smartling_connector_post_widget', 'smartling_connector_nonce' );


		// Display the form, using the current value.
		if ($post->post_content && $post->post_title) {

			require_once plugin_dir_path( __FILE__ ) . '/partials/smartling-connector-post-widget.php';

		} else {
			echo '<p>Need to save the post</p>';
		}
	}


	public function save_post( $post_id ) {
	
		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['smartling_connector_nonce'] ) )
			return $post_id;

		$nonce = $_POST['smartling_connector_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'smartling_connector_post_widget' ) )
			return $post_id;

		// If this is an autosave, our form has not been submitted,
                //     so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return $post_id;

		// Check the user's permissions.
		if ( 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) )
				return $post_id;
	
		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}

		/* OK, its safe for us to save the data now. */

		// Sanitize the user input.
		$data = $_POST['smartling_post_widget'];

		update_post_meta( $post_id, 'smartling_post_widget_data', $data );
		$this->save_to_db($post_id, $data);
	}


	public function save_to_db($post_id, array $data) {
		
	}


	public function get_target_locales() {

		$settings = new Smartling_Connector_Settings($plugin_name, $version);
		$locales  = $settings->get_target_locales();

		return $locales;
	}

}