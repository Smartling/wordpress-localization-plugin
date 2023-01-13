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
    /** @noinspection PhpUnused, used in DI */
    public function registerMigration(SmartlingDbMigrationInterface $migration): void
    {
        $this->registerHandler($migration->getVersion(), $migration);
    }

    /**
     * @return SmartlingDbMigrationInterface[]
     */
    public function getMigrations(int $fromVersion): array
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

    public function getLastMigration(): int
    {
        $ver = 0;

        foreach ($this->getCollection() as $version => $migration) {
            $ver = max($ver, $version);
        }

        return $ver;
    }
}
