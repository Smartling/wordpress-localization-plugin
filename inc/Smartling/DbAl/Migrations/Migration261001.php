<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\Submissions\SubmissionEntity;

/**
 * Stores the configuration profile a submission was requested with.
 *
 * Existing rows are left NULL: until stamped, they keep resolving the profile by source
 * blog. SubmissionManager::getSubmissionEntity() backfills the column with the currently
 * active profile the first time a pre-existing submission is (re)submitted for translation,
 * so an in-flight row stays NULL only until it is next touched by an upload/resubmit flow.
 */
class Migration261001 implements SmartlingDbMigrationInterface
{
    public function getVersion(): int
    {
        return 261001;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        $db = new DB();
        $tableName = $db->completeTableName(SubmissionEntity::getTableName());

        // Migration240315 may already have created the table with the current field definitions.
        $existingColumns = $db->getColumnArray("SHOW COLUMNS FROM `$tableName`");
        if (in_array(SubmissionEntity::FIELD_CONFIGURATION_PROFILE_ID, $existingColumns, true)) {
            return [];
        }

        return [
            sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                $tableName,
                SubmissionEntity::FIELD_CONFIGURATION_PROFILE_ID,
                SubmissionEntity::getFieldDefinitions()[SubmissionEntity::FIELD_CONFIGURATION_PROFILE_ID]
            ),
        ];
    }
}
