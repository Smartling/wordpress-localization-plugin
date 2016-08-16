<?php
/**
 * @link              https://www.smartling.com
 * @since             1.0.0
 * @package           smartling-connector
 * @wordpress-plugin
 * Plugin Name:       Smartling Connector
 * Plugin URI:        https://www.smartling.com/translation-software/wordpress-translation-plugin/
 * Description:       Integrate your Wordpress site with Smartling to upload your content and download translations.
 * Version:           1.2.4
 * Author:            Smartling
 * Author URI:        https://www.smartling.com
 * License:           GPL-2.0+
 * Network:           true
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smartling-connector
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Old-style code to run under PHP 5.2+
 */
class Smartling_Version_Check
{
    /**
     * Minimum version to run smartling plugin [major.minor]
     */
    const SMARTLING_MIN_PHP_VERSION = '5.4';

    public static function check_php_version()
    {
        $phpRequirements = explode('.', self::SMARTLING_MIN_PHP_VERSION);
        $phpMinVerId = reset($phpRequirements) * 10000 + end($phpRequirements) * 100;

        return (PHP_VERSION_ID >= $phpMinVerId);
    }

    public static function draw_php_low_version_message()
    {
        echo sprintf('<div class="error"><p>Smartling plugin requires at least %s PHP version to start.</p></div>', self::SMARTLING_MIN_PHP_VERSION);
    }
}

if (!Smartling_Version_Check::check_php_version()) {
    add_action('all_admin_notices', array('Smartling_Version_Check', 'draw_php_low_version_message'));
} else {
    if (class_exists('Smartling\Bootstrap', false)) {
        add_action('all_admin_notices', function () {
            $msg = vsprintf(
                'Smartling plugin (ver. %s) is already loaded. Skipping plugin load from %s.',
                array(Smartling\Bootstrap::getContainer()->getParameter('plugin.version'), __FILE__)
            );
            echo vsprintf('<div class="error"><p>%s</p></div>', array($msg));
        });
    } else {
        require_once plugin_dir_path(__FILE__) . 'inc/autoload.php';

        $bootstrap = new Smartling\Bootstrap();
        add_action('plugins_loaded', array($bootstrap, 'load',), 99);
        register_activation_hook(__FILE__, array($bootstrap, 'activate',));
        register_deactivation_hook(__FILE__, array($bootstrap, 'deactivate',));
        register_uninstall_hook(__FILE__, array('Smartling\Bootstrap', 'uninstall'));
    }
}
