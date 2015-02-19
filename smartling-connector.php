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
 * Plugin Name:       Smartling Connector
 * Plugin URI:        http://smartling.com/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress dashboard.
 * Version:           1.0.0 @2015/02/19
 * Author:            SmartLing
 * Author URI:        http://smartling.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smartling-connector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'inc/autoload.php';

use Smartling\Bootstrap;

$bootstrap = new Bootstrap();

add_action( 'plugins_loaded', array ( $bootstrap, 'load' ), 999 );

register_activation_hook( __FILE__, array ( $bootstrap, 'activate' ) );
register_deactivation_hook( __FILE__, array ( $bootstrap, 'deactivate' ) );
