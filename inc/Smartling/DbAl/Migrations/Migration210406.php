<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

class Migration210406 implements SmartlingDbMigrationInterface
{
    public function getVersion(): int
    {
        return 210406;
    }

    public function getQueries($tablePrefix = ''): array
    {
        return [
            sprintf(
                'ALTER TABLE `%s%s` ADD COLUMN `job_name` %s',
                $tablePrefix,
                SubmissionEntity::getTableName(),
                ConfigurationProfileEntity::DB_TYPE_STRING_STANDARD . ' ' .
                    ConfigurationProfileEntity::DB_TYPE_DEFAULT_EMPTYSTRING
            ),
        ];
    }
}
