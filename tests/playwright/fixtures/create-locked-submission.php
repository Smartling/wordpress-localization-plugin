<?php
/**
 * WP-CLI eval-file script: creates (or resets) a submission targeting a real
 * post on a target site, with "Lock all fields" and one individual field
 * locked, so translation-lock.spec.js has a Translation Lock meta box to open.
 *
 * Regression WP-1019: the Translation Lock popup's own CSRF nonce field used
 * to share its name with WP_List_Table's own bulk-action nonce field, so the
 * browser submitted two `_wpnonce` inputs and every save was silently
 * rejected. This fixture seeds a submission already in the "locked" state so
 * the E2E test can prove that unlocking it via the popup actually persists.
 *
 * Usage (run from plugin dir):
 *   E2E_TEST_POST_ID=<id> wp eval-file tests/playwright/fixtures/create-locked-submission.php --url=<site-url>
 *
 * Prints:
 *   E2E_LOCK_TARGET_BLOG_PATH=<slug>
 *   E2E_LOCK_TARGET_POST_ID=<id>
 *
 * Safe to run multiple times — idempotent (resets the same target post/submission to "locked").
 */

global $wpdb;

$sourceId = (int) getenv('E2E_TEST_POST_ID');
if ($sourceId <= 0) {
    WP_CLI::error('E2E_TEST_POST_ID env var must be set to an existing source post id.');
}

$blogsTable = $wpdb->base_prefix . 'blogs';
$targetBlog = $wpdb->get_row(
    "SELECT blog_id, path FROM {$blogsTable} WHERE blog_id != 1 ORDER BY blog_id LIMIT 1",
    ARRAY_A
);
if (!$targetBlog) {
    WP_CLI::error('No target site found - multisite must have at least one site besides the main one.');
}
$targetBlogId = (int) $targetBlog['blog_id'];
$targetBlogPath = trim($targetBlog['path'], '/');

switch_to_blog($targetBlogId);

$targetPost = get_page_by_path('e2e-lock-test-target', OBJECT, 'post');
if ($targetPost) {
    $targetPostId = $targetPost->ID;
} else {
    $targetPostId = wp_insert_post([
        'post_title'   => 'E2E Lock Test Target',
        'post_name'    => 'e2e-lock-test-target',
        'post_status'  => 'publish',
        'post_content' => 'E2E lock test content',
    ], true);
    if (is_wp_error($targetPostId)) {
        restore_current_blog();
        WP_CLI::error('Failed creating target post: ' . $targetPostId->get_error_message());
    }
}

restore_current_blog();

$submissionsTable = $wpdb->base_prefix . 'smartling_submissions';
$lockedFields = serialize(['entity/post_title']);

$existingSubmissionId = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$submissionsTable}
     WHERE source_blog_id = %d AND source_id = %d AND target_blog_id = %d AND target_id = %d AND content_type = %s",
    1,
    $sourceId,
    $targetBlogId,
    $targetPostId,
    'post'
));

if ($existingSubmissionId) {
    $wpdb->update(
        $submissionsTable,
        [
            'status'        => 'Completed',
            'is_locked'     => 1,
            'locked_fields' => $lockedFields,
        ],
        ['id' => $existingSubmissionId]
    );
} else {
    $now = current_time('mysql');
    $wpdb->insert($submissionsTable, [
        'source_title'    => 'E2E Test Post',
        'source_blog_id'  => 1,
        'content_type'    => 'post',
        'source_id'       => $sourceId,
        'target_locale'   => 'en-US',
        'target_blog_id'  => $targetBlogId,
        'target_id'       => $targetPostId,
        'submitter'       => 'wp',
        'submission_date' => $now,
        'applied_date'    => $now,
        'status'          => 'Completed',
        'is_locked'       => 1,
        'is_cloned'       => 0,
        'last_modified'   => $now,
        'outdated'        => 0,
        'last_error'      => '',
        'locked_fields'   => $lockedFields,
        'created_at'      => $now,
    ]);
    if (!$wpdb->insert_id) {
        WP_CLI::error('Failed creating locked submission: ' . $wpdb->last_error);
    }
}

WP_CLI::log("E2E_LOCK_TARGET_BLOG_PATH={$targetBlogPath}");
WP_CLI::log("E2E_LOCK_TARGET_POST_ID={$targetPostId}");
WP_CLI::success('Locked test submission ready.');
