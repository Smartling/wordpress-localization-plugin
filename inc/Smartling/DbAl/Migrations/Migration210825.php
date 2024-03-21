<?php

namespace Smartling\DbAl\Migrations;

use Smartling\DbAl\DB;
use Smartling\Submissions\SubmissionEntity;

class Migration210825 implements SmartlingDbMigrationInterface
{
    public function __construct(private DB $db)
    {
    }
    public function getVersion(): int
    {
        return 210825;
    }

    public function getQueries($tablePrefix = 'wp_'): array
    {
        $tableName = SubmissionEntity::getTableName();
        return [
            "CREATE INDEX {$tableName}_target_blog_id_target_id_idx
                ON {$this->db->completeTableName($tableName)} (target_blog_id, target_id)"
        ];
    }
}
