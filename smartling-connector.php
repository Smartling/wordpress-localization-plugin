<?php
/**
 * @link              http://example.com
 * @since             1.0.0
 * @package           smartling-connector
 *
 * @wordpress-plugin
 * Plugin Name:       Smartling Connector
 * Plugin URI:        https://www.smartling.com/translation-software/wordpress-translation-plugin/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress dashboard.
 * Version:           1.0.6-dev @2015/03/05
 * Author:            SmartLing
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

require_once plugin_dir_path( __FILE__ ) . 'inc/autoload.php';

use Smartling\Bootstrap;

$bootstrap = new Bootstrap();

add_action( 'plugins_loaded', array ( $bootstrap, 'load' ), 999 );

register_activation_hook( __FILE__, array ( $bootstrap, 'activate' ) );
register_deactivation_hook( __FILE__, array ( $bootstrap, 'deactivate' ) );
