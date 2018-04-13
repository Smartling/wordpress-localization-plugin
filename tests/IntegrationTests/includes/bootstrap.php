<?php
define('DIR_TESTDATA', getenv('TEST_DATA_DIR'));
define('WP_LANG_DIR', DIR_TESTDATA . '/languages');
define('WP_TESTS_TABLE_PREFIX', getenv('WP_DB_TABLE_PREFIX'));
define('DISABLE_WP_CRON', true);
define('WP_MEMORY_LIMIT', -1);
define('WP_MAX_MEMORY_LIMIT', -1);
define('REST_TESTS_IMPOSSIBLY_HIGH_NUMBER', 99999999);
if (!defined('WP_DEFAULT_THEME')) {
    define('WP_DEFAULT_THEME', 'default');
}
defined('MULTISITE') or define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
$config_file_path = getenv('TEST_CONFIG');
global $wpdb, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp, $phpmailer, $wp_theme_directories;
require_once __DIR__ . '/functions.php';
require_once $config_file_path;
tests_reset__SERVER();
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';
$multisite = true;
// Override the PHPMailer
require_once(dirname(__FILE__) . '/mock-mailer.php');
$phpmailer = new MockPHPMailer(true);
$wp_theme_directories = array(DIR_TESTDATA . '/themedir');
$GLOBALS['base'] = '/';
$GLOBALS['_wp_die_disabled'] = false;
// Allow tests to override wp_die
tests_add_filter('wp_die_handler', '_wp_die_handler_filter');
require_once ABSPATH . '/wp-settings.php';
require_once __DIR__ . "/../../../inc/autoload.php";
