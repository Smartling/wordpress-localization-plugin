<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\Jobs\JobInformationEntity;
use Smartling\Submissions\SubmissionEntity;

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
            ($db)->prepareSql([
                'columns' => JobInformationEntity::getFieldDefinitions(),
                'indexes' => JobInformationEntity::getIndexes(),
                'name' => JobInformationEntity::getTableName(),
            ]),
            sprintf(
                'INSERT INTO %s%s (%s, %s) select %s, %s from %s%s',
                $tablePrefix,
                JobInformationEntity::getTableName(),
                JobInformationEntity::FIELD_SUBMISSION_ID,
                JobInformationEntity::FIELD_BATCH_UID,
                SubmissionEntity::FIELD_ID,
                JobInformationEntity::FIELD_BATCH_UID,
                $tablePrefix,
                SubmissionEntity::getTableName()
            ),
        ];
    }
}
