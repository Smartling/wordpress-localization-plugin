<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\SubmissionJobEntity;

class Migration210406 implements SmartlingDbMigrationInterface
{
    public function getVersion(): int
    {
        return 210406;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        $db = new DB();
        return [
            $db->prepareSql([
                'columns' => JobEntity::getFieldDefinitions(),
                'indexes' => JobEntity::getIndexes(),
                'name' => JobEntity::getTableName(),
            ]),
            $db->prepareSql([
                'columns' => SubmissionJobEntity::getFieldDefinitions(),
                'indexes' => SubmissionJobEntity::getIndexes(),
                'name' => SubmissionJobEntity::getTableName(),
            ]),
        ];
    }
}
