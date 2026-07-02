<?php
/**
 * WP-CLI eval-file script: creates a minimal Smartling configuration profile
 * in the database so the job wizard renders during E2E tests.
 *
 * Usage (run from plugin dir):
 *   wp eval-file tests/playwright/fixtures/create-profile.php --url=<site-url>
 *
 * Reads CRE_PROJECT_ID, CRE_USER_IDENTIFIER, CRE_TOKEN_SECRET from the
 * environment (same vars used by PHPUnit integration tests).
 * Safe to run multiple times — idempotent.
 */

global $wpdb;

$table = $wpdb->base_prefix . 'smartling_configuration_profiles';

$exists = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE original_blog_id = %d AND profile_name = %s",
        1,
        'E2E Test Profile'
    )
);

if ($exists > 0) {
    WP_CLI::log('E2E Test Profile already exists — skipping.');
    return;
}

$projectId      = getenv('CRE_PROJECT_ID')      ?: 'aabbccdd1';
$userIdentifier = getenv('CRE_USER_IDENTIFIER') ?: 'e2e-test-user';
$secretKey      = getenv('CRE_TOKEN_SECRET')    ?: 'e2e-test-secret';

$result = $wpdb->insert(
    $table,
    [
        'profile_name'                     => 'E2E Test Profile',
        'project_id'                       => $projectId,
        'user_identifier'                  => $userIdentifier,
        'secret_key'                       => $secretKey,
        'is_active'                        => 1,
        'original_blog_id'                 => 1,
        'auto_authorize'                   => 0,
        'retrieval_type'                   => 'published',
        'upload_on_update'                 => 0,
        'publish_completed'                => 1,
        'download_on_change'               => 0,
        'clean_metadata_on_download'       => 0,
        'always_sync_images_on_upload'     => 0,
        'target_locales'                   => '[]',
        'filter_skip'                      => null,
        'filter_copy_by_field_name'        => null,
        'filter_copy_by_field_value_regex' => null,
        'filter_flag_seo'                  => null,
        'clone_attachment'                 => 0,
        'enable_notifications'             => 0,
        'filter_field_name_regexp'         => 0,
    ],
    [
        '%s', '%s', '%s', '%s',
        '%d', '%d', '%d', '%s',
        '%d', '%d', '%d', '%d',
        '%d', '%s', '%s', '%s',
        '%s', '%s', '%d', '%d', '%d',
    ]
);

if ($result !== false) {
    WP_CLI::success('Created E2E Test Profile (ID: ' . $wpdb->insert_id . ')');
} else {
    WP_CLI::error('Failed to create E2E Test Profile: ' . $wpdb->last_error);
}
