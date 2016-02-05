<?php

namespace Smartling\DbAl\Migrations;

use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class DbMigrationManager
 *
 * @package Smartling\DbAl\Migrations
 */
class DbMigrationManager extends SmartlingFactoryAbstract
{

    public function registerMigration(SmartlingDbMigrationInterface $migration)
    {
        $this->registerHandler($migration->getVersion(), $migration);
    }

    public function getMigrations($fromVersion)
    {
        $pool = [];
        foreach ($this->getCollection() as $version => $migration) {
            if ($version > $fromVersion) {
                $pool[] = $migration;
            }
        }
        ksort($pool);

        return $pool;
    }

    public function getLastMigration()
    {
        $ver = 0;

        foreach ($this->getCollection() as $version => $migration) {
            $ver = max($ver, $version);
        }

        return $ver;
    }
}