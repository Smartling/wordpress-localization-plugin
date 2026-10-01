#!/usr/bin/env bash
#
# Setup script for local integration test database.
#
# Run once (or when you want a clean slate). Creates the wordpress_test database
# by importing the production wordpress database schema and data, then renames
# all tables from the production prefix (wp_) to the test prefix (wptests_).
#
# Also creates tests/wp-test-install/ — a minimal WordPress directory with its
# own wp-config.php pointing to the test database, used by wp-cli during tests
# (for cron execution and db:query calls).
#
# Usage:
#   cp tests/.env.local.example tests/.env.local
#   # Fill in your values in tests/.env.local
#   bash tests/setup-local-test-db.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$SCRIPT_DIR/.env.local"

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: $ENV_FILE not found."
    echo "Copy tests/.env.local.example to tests/.env.local and fill in your values."
    exit 1
fi

# Load env vars
set -a
source "$ENV_FILE"
set +a

# Defaults
WP_DB_USER="${WP_DB_USER:-root}"
WP_DB_PASS="${WP_DB_PASS:-}"
WP_DB_HOST="${WP_DB_HOST:-127.0.0.1}"
WP_DB_NAME="${WP_DB_NAME:-wordpress_test}"
WP_DB_TABLE_PREFIX="${WP_DB_TABLE_PREFIX:-wptests_}"
WP_INSTALL_DIR="${WP_INSTALL_DIR:-/opt/homebrew/var/www}"
WPCLI_PATH="${WPCLI_PATH:-$SCRIPT_DIR/wp-test-install}"
SOURCE_DB="${SOURCE_DB:-wordpress}"
SOURCE_PREFIX="${SOURCE_PREFIX:-wp_}"

MYSQL_ARGS="-u $WP_DB_USER -h $WP_DB_HOST"
if [ -n "$WP_DB_PASS" ]; then
    MYSQL_ARGS="$MYSQL_ARGS -p$WP_DB_PASS"
fi

echo "=== Step 1: Create test database '$WP_DB_NAME' ==="
mysql $MYSQL_ARGS -e "DROP DATABASE IF EXISTS \`$WP_DB_NAME\`; CREATE DATABASE \`$WP_DB_NAME\` CHARACTER SET utf8 COLLATE utf8_unicode_ci;"

echo "=== Step 2: Import production schema and data from '$SOURCE_DB' ==="
# --set-gtid-purged=OFF: required for MySQL servers with GTID mode enabled (MySQL 8+)
# --single-transaction: consistent snapshot without locking tables
mysqldump $MYSQL_ARGS --set-gtid-purged=OFF --single-transaction "$SOURCE_DB" | mysql $MYSQL_ARGS "$WP_DB_NAME"

echo "=== Step 3: Rename tables from '${SOURCE_PREFIX}' prefix to '${WP_DB_TABLE_PREFIX}' prefix ==="
# Build and execute RENAME TABLE statements dynamically
RENAME_SQL=$(mysql $MYSQL_ARGS -N "$WP_DB_NAME" -e "
    SELECT CONCAT('RENAME TABLE \`', TABLE_NAME, '\` TO \`', REPLACE(TABLE_NAME, '${SOURCE_PREFIX}', '${WP_DB_TABLE_PREFIX}'), '\`;')
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '${WP_DB_NAME}'
    ORDER BY TABLE_NAME;
")

if [ -z "$RENAME_SQL" ]; then
    echo "ERROR: No tables found to rename in $WP_DB_NAME. Import may have failed."
    exit 1
fi

echo "$RENAME_SQL" | mysql $MYSQL_ARGS "$WP_DB_NAME"

echo "=== Step 4: Verify tables ==="
TABLE_COUNT=$(mysql $MYSQL_ARGS -N "$WP_DB_NAME" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$WP_DB_NAME';")
echo "Tables in $WP_DB_NAME: $TABLE_COUNT"

echo "=== Step 5: Create test WordPress install dir at '$WPCLI_PATH' ==="
mkdir -p "$WPCLI_PATH"

# Create wp-config.php pointing to the test database
cat > "$WPCLI_PATH/wp-config.php" << WPCONFIG
<?php
define('DB_NAME',     '${WP_DB_NAME}');
define('DB_USER',     '${WP_DB_USER}');
define('DB_PASSWORD', '${WP_DB_PASS}');
define('DB_HOST',     '${WP_DB_HOST}');
define('DB_CHARSET',  'utf8');
define('DB_COLLATE',  '');

define('AUTH_KEY',         'local-test-key-1');
define('SECURE_AUTH_KEY',  'local-test-key-2');
define('LOGGED_IN_KEY',    'local-test-key-3');
define('NONCE_KEY',        'local-test-key-4');
define('AUTH_SALT',        'local-test-salt-1');
define('SECURE_AUTH_SALT', 'local-test-salt-2');
define('LOGGED_IN_SALT',   'local-test-salt-3');
define('NONCE_SALT',       'local-test-salt-4');

\$table_prefix = '${WP_DB_TABLE_PREFIX}';

define('WP_DEBUG', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DOMAIN_CURRENT_SITE', '${WP_INSTALLATION_DOMAIN:-localhost}');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);
define('WP_ALLOW_MULTISITE', true);

define('ABSPATH', '${WP_INSTALL_DIR}/');
# Point wp-content to production dir using a constant instead of a symlink.
# Using a symlink would cause PHPUnit to follow it back into the plugin source
# tree and pick up vendor *Test.php files from inc/lib/.
define('WP_CONTENT_DIR', '${WP_INSTALL_DIR}/wp-content');
define('WP_CONTENT_URL', 'http://${WP_INSTALLATION_DOMAIN:-localhost}/wp-content');
require_once ABSPATH . 'wp-settings.php';
WPCONFIG

# Symlink WordPress core directories into the test install dir (no wp-content symlink!)
for item in wp-includes wp-admin; do
    target="$WPCLI_PATH/$item"
    if [ -L "$target" ]; then
        rm "$target"
    fi
    ln -sf "$WP_INSTALL_DIR/$item" "$target"
done

# Symlink individual root PHP files needed by wp-cli
for phpfile in index.php wp-load.php wp-blog-header.php wp-settings.php; do
    target="$WPCLI_PATH/$phpfile"
    if [ -L "$target" ]; then
        rm "$target"
    fi
    ln -sf "$WP_INSTALL_DIR/$phpfile" "$target"
done

echo ""
echo "=== Setup complete! ==="
echo ""
echo "Test database '$WP_DB_NAME' is ready with ${TABLE_COUNT} tables (prefix: ${WP_DB_TABLE_PREFIX})."
echo "Test WordPress install at: $WPCLI_PATH"
echo ""
echo "Next steps:"
echo "  1. Fill in Smartling API credentials in tests/.env.local"
echo "  2. Run tests: ./run-integration-tests.sh"
