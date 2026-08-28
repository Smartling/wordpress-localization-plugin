<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Base\SmartlingEntityAbstract;
use Smartling\DbAl\DB;
use Smartling\Models\UploadQueueEntity;

/**
 * Adds claim tracking to the upload queue.
 *
 * Queue rows used to be deleted the moment they were handed to the upload job, so a
 * fatal error, timeout or out of memory during the upload that followed destroyed the
 * queued work without a trace. Rows are now claimed instead, and only removed once the
 * upload has been accounted for.
 */
class Migration260825 implements SmartlingDbMigrationInterface
{
    public function getVersion(): int
    {
        return 260825;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        $db = new DB();
        $tableName = $db->completeTableName(UploadQueueEntity::getTableName());

        // Migration240315 (re)creates this table with `CREATE TABLE IF NOT EXISTS` from the
        // current, evolving UploadQueueEntity::getFieldDefinitions(). A site upgrading from a
        // schema version older than 240315 runs that migration first, and it already creates
        // the table with the columns below, so blindly adding them here would fail with a
        // duplicate column error. Only add what isn't already there.
        $existingColumns = $db->getColumnArray("SHOW COLUMNS FROM `$tableName`");

        $columns = [
            UploadQueueEntity::FIELD_CLAIMED => SmartlingEntityAbstract::DB_TYPE_DATETIME_NULL,
            UploadQueueEntity::FIELD_ATTEMPTS => SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_ZERO,
        ];

        $queries = [];
        foreach ($columns as $column => $definition) {
            if (!in_array($column, $existingColumns, true)) {
                $queries[] = sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $tableName, $column, $definition);
            }
        }

        return $queries;
    }
}
