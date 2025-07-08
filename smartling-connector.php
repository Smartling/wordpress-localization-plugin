<?php

use Smartling\Bootstrap;
use Smartling\RestApi;

/**
 * @link              https://www.smartling.com
 * @since             1.0.0
 * @package           smartling-connector
 * @wordpress-plugin
 * Plugin Name:       Smartling Connector
 * Plugin URI:        https://www.smartling.com/products/automate/integrations/wordpress/
 * Description:       Integrate your WordPress site with Smartling to upload your content and download translations.
 * Version:           3.12.0
 * Author:            Smartling
 * Author URI:        https://www.smartling.com
 * License:           GPL-2.0+
 * Network:           true
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smartling-connector
 * Domain Path:       /languages
 * Requires PHP:      8.0
 * Elementor tested up to: 3.20.2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
/**
 * Execute everything only on admin pages or while running cron tasks
 */
if (is_admin() || (defined('DOING_CRON') && true === DOING_CRON)) {
    if (PHP_MAJOR_VERSION < 8) {
        add_action('all_admin_notices', static function() {
            echo '<div class="error"><p>Smartling plugin requires at least PHP version 8 to start.</p></div>';
        });
    } elseif (class_exists(Bootstrap::class, false)) {
        add_action('all_admin_notices', static function () {
            echo sprintf('<div class="error"><p>Smartling plugin (ver. %s) is already loaded. Skipping plugin load from %s</p></div>',
                Bootstrap::getContainer()->getParameter('plugin.version'),
                __FILE__,
            );
        });
    } else {
        require_once plugin_dir_path(__FILE__) . 'inc/autoload.php';
        $pluginData = get_file_data(__FILE__, ['Version' => 'Version']);
        Bootstrap::$pluginVersion = $pluginData['Version'];
        $bootstrap = new Bootstrap();
        add_action('plugins_loaded', array($bootstrap, 'load',), 99);
        register_activation_hook(__FILE__, array($bootstrap, 'activate',));
        register_deactivation_hook(__FILE__, array($bootstrap, 'deactivate',));
        register_uninstall_hook(__FILE__, array(Bootstrap::class, 'uninstall'));
    }
}

require_once plugin_dir_path(__FILE__) . 'inc/autoload.php';
(new RestApi())->register_routes();
