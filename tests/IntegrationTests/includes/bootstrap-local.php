<?php

/**
 * Local bootstrap for integration tests (non-Docker).
 *
 * Loads environment variables from tests/.env.local before delegating to the
 * standard bootstrap.php. This allows running tests directly from a terminal
 * or from PhpStorm without manually setting environment variables.
 *
 * Required env vars (set in tests/.env.local):
 *   WP_INSTALL_DIR, WP_DB_NAME, WP_DB_USER, WP_DB_PASS, WP_DB_HOST,
 *   WP_DB_TABLE_PREFIX, WP_INSTALLATION_DOMAIN, TEST_DATA_DIR, TEST_CONFIG,
 *   WPCLI, WPCLI_PATH, CRE_PROJECT_ID, CRE_USER_IDENTIFIER, CRE_TOKEN_SECRET, SITES
 */

$envFile = __DIR__ . '/../../.env.local';

if (file_exists($envFile) && !getenv('WP_INSTALL_DIR')) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

if (!getenv('WP_INSTALL_DIR')) {
    fwrite(STDERR, "ERROR: WP_INSTALL_DIR is not set.\n");
    fwrite(STDERR, "Copy tests/.env.local.example to tests/.env.local and fill in your values.\n");
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';
