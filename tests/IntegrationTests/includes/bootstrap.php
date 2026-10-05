<?php
define('DIR_TESTDATA', getenv('TEST_DATA_DIR'));
const WP_LANG_DIR = DIR_TESTDATA . '/languages';
define('WP_TESTS_TABLE_PREFIX', getenv('WP_DB_TABLE_PREFIX'));
const DISABLE_WP_CRON = true;
const WP_MEMORY_LIMIT = - 1;
const WP_MAX_MEMORY_LIMIT = - 1;
if (!defined('WP_DEFAULT_THEME')) {
    define('WP_DEFAULT_THEME', 'default');
}
defined('MULTISITE') or define('MULTISITE', true);
const SUBDOMAIN_INSTALL = false;
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

/*
 * smartling-connector.php only hooks Bootstrap::load() onto 'plugins_loaded' when
 * is_admin() || DOING_CRON, neither of which is true in this CLI/PHPUnit context, so it
 * never runs on its own here. Load it once, now, before any test's setUp() runs: the WP
 * core test base class (WP_UnitTestCase::setUp()) snapshots $wp_filter on the very first
 * test and restores that snapshot after every test (_backup_hooks()/_restore_hooks()), so
 * any hook this registers after that point (e.g. from a test calling this again, or from a
 * service lazily constructed mid-test) gets wiped at that test's tearDown and never comes
 * back once the owning singleton is already cached. Loading here means the snapshot itself
 * already includes every hook the plugin registers, so they survive for the whole run.
 */
(new Smartling\Bootstrap())->load();
