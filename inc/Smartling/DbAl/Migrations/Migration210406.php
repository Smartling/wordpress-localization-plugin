<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\Jobs\JobInformationEntity;
use Smartling\Jobs\SubmissionJobEntity;

class Migration210406 implements SmartlingDbMigrationInterface
{
    public function getVersion(): int
    {
        return 210406;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        $classes = [JobInformationEntity::class, SubmissionJobEntity::class];
        $db = new DB();
        $queries = [];
        foreach ($classes as $class) {
            /** @noinspection PhpUndefinedMethodInspection */
            $queries[] = $db->prepareSql([
                'columns' => $class::getFieldDefinitions(),
                'indexes' => $class::getIndexes(),
                'name' => $class::getTableName(),
            ]);
        }
        return $queries;
    }
}
