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
class Smartling_Connector_Settings {

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


	private $locale_langs;


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @var      string    $plugin_name       The name of this plugin.
	 * @var      string    $version    The version of this plugin.
	 */


	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the Dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/smartling-connector-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the dashboard.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Admin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Admin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/smartling-connector-admin.js', array( 'jquery' ), $this->version, false );

	}


	public function localize_scripts() {
		
		$key = get_option('localization_option');

		if( isset( $key['field_project_key'] ) )
		wp_localize_script('jquery', 'localizationApiKey', $key['field_project_key'] );

	}


	public function register_settings() {

		// args: $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position
		add_menu_page( 'Smartling Connector', 'Smartling Connector', 'Administrator', 'smartling-settings', array( $this, 'render_options_page')  );
	}

	public function render_options_page() {

		require_once plugin_dir_path( __FILE__ ) . '/partials/smartling-connector-settings.php';

	}

	public function save_settings() {

		#var_dump($_REQUEST['smartling_settings']);
		if ( !isset( $_POST['smartling_connector_nonce'])) {
			wp_redirect($_POST['_wp_http_referer']);
		}

		$nonce = $_POST['smartling_connector_nonce'];

		if ( ! wp_verify_nonce( $nonce, 'smartling_connector_settings' ) )
			wp_redirect($_POST['_wp_http_referer']);
		
		$settings = $_POST['smartling_settings'];

		foreach ($settings as $key => $value) {

			$option = get_site_option($key);

			if (!$option ) {

				add_site_option($key, $value);
			
			} else {
				
				update_site_option($key, $value);
			}

		}

		wp_redirect($_REQUEST["_wp_http_referer"]);
	}


	public function get_target_locales() {
		#global $wpdb;

		$lang_list = get_site_option( 'inpsyde_multilingual' );
		$texts     = array();

		foreach ($lang_list as $key => $value) {

			$value = $value['text'];
			#$value = "'$value'";
			array_push( $texts, $value );
		}

		return $texts;
	}



	public function set_default_locale($args) {

		$val = get_option('localization_option');

		if( isset( $val['field_default_locale'] ) )
		$val = $val['field_default_locale'];

		?>

		<p>Site default language is: <?php echo $val; ?></p>
		<p><a href="#" id="chenge-default-locale">Change default locale</a></p>
		<br>
		<select name="localization_option[field_default_locale]"  id="default-locales">
			<?php foreach ($this->locale_langs as $key => $value):  ?>
				<option value="<?php echo esc_attr($value) ?>" <?php selected( $val, $value ); ?> > <?php echo $value; ?> </option>
			<?php endforeach; ?>
		</select>
		<?php
		
	}

	public function fields_validation($args) {
		
		#var_dump($args);
		#$log = implode('=>',$args);
		#file_put_contents('validation.log', $log.'<<!end');
		#if ($args === '' || is_null($args)) {
		#	add_settings_error( 'field_project_id', 'code-errore', 'Errore in field', 'error' );
		#}
			#settings_errors( 'field_project_id');

		return $args;
	}

}