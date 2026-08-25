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
        $tableName = (new DB())->completeTableName(UploadQueueEntity::getTableName());

        return [
            sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                $tableName,
                UploadQueueEntity::FIELD_CLAIMED,
                SmartlingEntityAbstract::DB_TYPE_DATETIME_NULL,
            ),
            sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                $tableName,
                UploadQueueEntity::FIELD_ATTEMPTS,
                SmartlingEntityAbstract::DB_TYPE_U_BIGINT . ' ' . SmartlingEntityAbstract::DB_TYPE_DEFAULT_ZERO,
            ),
        ];
    }
}
