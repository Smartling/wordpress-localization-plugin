<?php
/**
 * Plugin Name: Smartling Example MLP3 to Smartling Connector migration
 * Plugin URI: http://smartling.com
 * Author: Smartling
 * Description: Copy relations from MLP3 tables to Smartling tables, taking first active profile as source blog
 * Version: 1.0
 */

use Smartling\DbAl\DB;
use Smartling\DbAl\DummyLocalizationPlugin;
use Smartling\DbAl\Migrations\DbMigrationManager;
use Smartling\DbAl\MultilingualPress3Connector;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Jobs\JobManager;
use Smartling\Jobs\SubmissionsJobsManager;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

$log = [];
$showForm = true;
add_action('admin_menu', static function () use ($log, $showForm) {
    $db = new DB(new DbMigrationManager());
    $localizationProxy = new MultilingualPress3Connector();
    $pageSize = 20;
    $siteHelper = new SiteHelper();
    $settingsManager = new SettingsManager($db, $pageSize, $siteHelper, $localizationProxy);
    $profile = ArrayHelper::first($settingsManager->getActiveProfiles());
    $sourceBlogId = $profile->getSourceLocale()->getBlogId();
    $slug = 'migrate_mlp3_data_to_smartling';
    $title = 'Migrate MLP3 data to Smartling';
    if (($_POST['action'] ?? '') === 'Migrate' && wp_verify_nonce($_POST['nonce'] ?? '', $slug)) {
        global $wpdb;
        $start = (int)get_option($slug);
        $max = getMaxRelationshipId();
        $submissionsJobsManager = new SubmissionsJobsManager($db);
        $submissionManager = new SubmissionManager($db, $pageSize, new JobManager($db, $submissionsJobsManager), new DummyLocalizationPlugin(), $siteHelper, $submissionsJobsManager);
        $tablePrefix = $wpdb->prefix . 'mlp_';
        while ($start < $max) {
            $relationship = $wpdb->get_results("SELECT relationship_id, type " .
                "FROM {$tablePrefix}content_relations cr " .
                "INNER JOIN {$tablePrefix}relationships r ON cr.relationship_id = r.id " .
                "WHERE relationship_id > $start ORDER BY relationship_id LIMIT 1", ARRAY_A)[0];
            $relationshipId = (int)$relationship['relationship_id'];
            $type = $relationship['type'];
            $relation = ['source' => null, 'targets' => []];
            foreach ($wpdb->get_results(
                "SELECT site_id, content_id FROM wp_mlp_content_relations WHERE relationship_id = $relationshipId",
                ARRAY_A
            ) as $result) {
                $contentId = (int)$result['content_id'];
                $siteId = (int)$result['site_id'];
                if ($siteId === $sourceBlogId) {
                    $relation['source'] = $contentId;
                } else {
                    $relation['targets'][] = ['siteId' => $siteId, 'contentId' => $contentId];
                }
            }
            if ($relation['source'] !== null) { // Relationship linked to the active profile?
                foreach ($relation['targets'] as $target) {
                    $submission = $submissionManager->getSubmissionEntity(
                        $type,
                        $sourceBlogId,
                        $relation['source'],
                        $target['siteId'],
                        $localizationProxy,
                        $target['contentId']
                    );
                    if (!$submissionManager->submissionExists(
                        $submission->getContentType(),
                        $submission->getSourceBlogId(),
                        $submission->getSourceId(),
                        $submission->getTargetBlogId())
                    ) {
                        $submissionManager->storeEntity($submission);
                    } else {
                        $log[] = "Skipped adding submission for {$submission->getContentType()} " .
                            "{$submission->getSourceId()}: already exists in target blog {$submission->getTargetBlogId()}";
                    }
                }
            }
            $start = $relationshipId;
            update_option($slug, $start);
        }
        $showForm = false;
    }
    add_options_page($title, $title, 'manage_options', $slug, static function () use ($log, $slug, $showForm) {
        $lastProcessedRelationshipId = get_option($slug);
        $logString = implode("<br>", $log);
        $maxRelationshipId = getMaxRelationshipId();
        $nonce = wp_create_nonce($slug);
        echo $logString;
        if ($showForm) {
            echo <<<HTML
<form method="post">
<p>Last processed id: $lastProcessedRelationshipId, max id: $maxRelationshipId</p>
<input type="hidden" value="$nonce" name="nonce">
<input type="submit" value="Migrate" name="action">
</form>
HTML;
        } else {
            echo "<h1>Migration complete!</h1>";
        }
    });
});

function getMaxRelationshipId(): int
{
    global $wpdb;
    return (int)($wpdb->get_results("SELECT MAX(relationship_id) max FROM wp_mlp_content_relations", ARRAY_A)[0]['max'] ?? 0);
}
