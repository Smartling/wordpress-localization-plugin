<?php

/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define('ABSPATH', getenv('WP_INSTALL_DIR') . '/');

/*
 * Path to the theme to test with.
 */
defined('WP_DEFAULT_THEME') or define('WP_DEFAULT_THEME', 'default');
define('WP_DEBUG', true);

define('DB_NAME',     getenv('WP_DB_NAME'));
define('DB_USER',     getenv('WP_DB_USER'));
define('DB_PASSWORD', getenv('WP_DB_PASS'));
define('DB_HOST',     getenv('WP_DB_HOST') ?: '127.0.0.1');
define('DB_CHARSET',  'utf8');
define('DB_COLLATE',  '');

/**#@+
 * Authentication Unique Keys and Salts.
 */
define('AUTH_KEY',         'local-test-key-1');
define('SECURE_AUTH_KEY',  'local-test-key-2');
define('LOGGED_IN_KEY',    'local-test-key-3');
define('NONCE_KEY',        'local-test-key-4');
define('AUTH_SALT',        'local-test-salt-1');
define('SECURE_AUTH_SALT', 'local-test-salt-2');
define('LOGGED_IN_SALT',   'local-test-salt-3');
define('NONCE_SALT',       'local-test-salt-4');

global $table_prefix;
$table_prefix = getenv('WP_DB_TABLE_PREFIX') ?: 'wptests_';

define('WP_TESTS_DOMAIN',  getenv('WP_INSTALLATION_DOMAIN') ?: 'localhost');
define('WP_TESTS_EMAIL',   'admin@example.org');
define('WP_TESTS_TITLE',   'Test Blog');

define('WP_PHP_BINARY', 'php');

define('WPLANG', '');
