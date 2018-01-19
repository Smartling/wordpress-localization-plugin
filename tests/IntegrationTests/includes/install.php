<?php
error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );
$config_file_path = $argv[1];
$multisite = ! empty( $argv[2] );
define( 'WP_INSTALLING', true );
require_once $config_file_path;
require_once dirname( __FILE__ ) . '/functions.php';
// Set the theme to our special empty theme, to avoid interference from the current Twenty* theme.
if ( ! defined( 'WP_DEFAULT_THEME' ) ) {
	define( 'WP_DEFAULT_THEME', 'default' );
}
tests_reset__SERVER();
$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';
tests_add_filter( 'wp_die_handler', '_wp_die_handler_filter_exit' );
require_once ABSPATH . '/wp-settings.php';
require_once ABSPATH . '/wp-admin/includes/upgrade.php';
require_once ABSPATH . '/wp-includes/wp-db.php';
// Override the PHPMailer
global $phpmailer;
require_once( dirname( __FILE__ ) . '/mock-mailer.php' );
$phpmailer = new MockPHPMailer();
register_theme_directory( dirname( __FILE__ ) . '/../data/themedir1' );
$wpdb->select( DB_NAME, $wpdb->dbh );