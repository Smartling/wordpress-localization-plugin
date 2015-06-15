<?php
/**
 * @link              http://example.com
 * @since             1.0.0
 * @package           smartling-connector
 *
 * @wordpress-plugin
 * Plugin Name:       Smartling Connector
 * Plugin URI:        https://www.smartling.com/translation-software/wordpress-translation-plugin/
 * Description:       Integrate your Wordpress site with Smartling to upload your content and download translations.
 * Version:           1.0.18@2015-06-15
 * Author:            Smartling
 * Author URI:        https://www.smartling.com
 * License:           GPL-2.0+
 * Network:           true
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smartling-connector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( class_exists( 'Smartling\Bootstrap', false ) ) {
	add_action( 'all_admin_notices', function () {
		$msg = vsprintf(
			'Smartling plugin (ver. %s) is already loaded. Skipping plugin load from %s.',
			array
			(
				Smartling\Bootstrap::getContainer()->getParameter( 'plugin.version' ),
				__FILE__
			)
		);
		echo vsprintf( '<div class="error"><p>%s</p></div>', array ( $msg ) );
	} );
} else {
	require_once plugin_dir_path( __FILE__ ) . 'inc/autoload.php';

	$bootstrap = new Smartling\Bootstrap();

	add_action( 'plugins_loaded', array ( $bootstrap, 'load' ), 999 );

	register_activation_hook( __FILE__, array ( $bootstrap, 'activate' ) );
	register_deactivation_hook( __FILE__, array ( $bootstrap, 'deactivate' ) );
}

