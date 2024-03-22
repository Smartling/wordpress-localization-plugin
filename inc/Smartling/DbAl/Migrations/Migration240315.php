<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\Models\UploadQueueItem;

class Migration240315 implements SmartlingDbMigrationInterface
{

    public function getVersion(): int
    {
        return 240315;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        return [
            (new DB(new DbMigrationManager()))->prepareSql([
                'columns' => UploadQueueItem::getFieldDefinitions(),
                'indexes' => UploadQueueItem::getIndexes(),
                'name' => UploadQueueItem::getTableName(),
            ]),
        ];
    }
}
