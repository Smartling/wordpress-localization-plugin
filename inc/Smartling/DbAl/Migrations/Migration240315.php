<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\Models\UploadQueueEntity;

class Migration240315 implements SmartlingDbMigrationInterface
{

    public function getVersion(): int
    {
        return 240315;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        return [
            (new DB())->prepareSql([
                'columns' => UploadQueueEntity::getFieldDefinitions(),
                'indexes' => UploadQueueEntity::getIndexes(),
                'name' => UploadQueueEntity::getTableName(),
            ]),
        ];
    }
}
