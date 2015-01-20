<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Dashboard. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Plugin_Name
 *
 * @wordpress-plugin
 * Plugin Name:       WordPress Smartling Connector
 * Plugin URI:        http://webinerds.com/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress dashboard.
 * Version:           1.0.0
 * Author:            Webinerds
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smartling-connector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// run Smartling autoloader
require_once plugin_dir_path( __FILE__ ) . 'inc/autoload.php';

use Smartling\Bootstrap;

$bootstrap = new Bootstrap();

add_action('plugins_loaded', array($bootstrap,'detectMultilangPlugins'), 999);


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-smartling-connector-activator.php
 */
function activate_smartling_connector() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-smartling-connector-activator.php';
	Smartling_Connector_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-smartling-connector-deactivator.php
 */
function deactivate_smartling_connector() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-smartling-connector-deactivator.php';
	Smartling_Connector_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_smartling_connector' );
register_deactivation_hook( __FILE__, 'deactivate_smartling_connector' );

/**
 * The core plugin class that is used to define internationalization,
 * dashboard-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-smartling-connector.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */

function run_smartling_connector() {

	$plugin = new Smartling_Connector();
	$plugin->run();
	// $xml = $plugin->xml();
	// $title = $xml->getPost(8156);
	#var_dump($title);
}

run_smartling_connector();
