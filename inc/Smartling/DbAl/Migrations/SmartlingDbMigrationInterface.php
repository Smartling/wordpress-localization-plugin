<?php

namespace Smartling\DbAl\Migrations;

interface SmartlingDbMigrationInterface
{
    /**
     * @return int
     */
    public function getVersion();

    /**
     * @param string $tablePrefix
     *
     * @return array
     */
    public function getQueries($tablePrefix = '');
}