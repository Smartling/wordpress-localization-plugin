<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\Migrations\DbMigrationManager;
use Smartling\DbAl\Migrations\SmartlingDbMigrationInterface;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Queue\Queue;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPInstallableInterface;

/**
 * Class DB
 *
 * @package Smartling\DbAl
 */
class DB implements SmartlingToCMSDatabaseAccessWrapperInterface, WPInstallableInterface
{

    const SMARTLING_DB_SCHEMA_VERSION = 'smartling_db_ver';

    /**
     * Plugin tables definition based on array
     *
     * @var array
     */
    private $tables = [];

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $needSqlLog = false;

    /**
     * @param LoggerInterface $logger
     * @param bool            $needLogRawSql
     */
    public function __construct(LoggerInterface $logger, $needLogRawSql)
    {
        $this->needSqlLog = (bool)$needLogRawSql;
        $this->logger = $logger;
        $this->buildTableDefinitions();
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * @return DbMigrationManager
     */
    public function getMigrationManager()
    {
        /**
         * @var DbMigrationManager $mgr
         */
        return Bootstrap::getContainer()->get('manager.db.migrations');
    }

    /**
     * @return \wpdb
     */
    public function getWpdb()
    {
        return $this->wpdb;
    }

    private function buildTableDefinitions()
    {
        // Submissions
        $this->tables[] = [
            'name'    => SubmissionEntity::getTableName(),
            'columns' => SubmissionEntity::getFieldDefinitions(),
            'indexes' => SubmissionEntity::getIndexes(),
        ];
        // Configuration profiles
        $this->tables[] = [
            'name'    => ConfigurationProfileEntity::getTableName(),
            'columns' => ConfigurationProfileEntity::getFieldDefinitions(),
            'indexes' => ConfigurationProfileEntity::getIndexes(),
        ];
        // Queue
        $this->tables[] = [
            'name'    => Queue::getTableName(),
            'columns' => Queue::getFieldDefinitions(),
            'indexes' => Queue::getIndexes(),
        ];
    }




    private function installDb()
    {
        foreach ($this->tables as $tableDefinition) {
            $query = $this->prepareSql($tableDefinition);
            $this->logger->info(vsprintf('Installing tables: %s', [$query]));
            $this->getWpdb()->query($query);
        }
        $currentDbVersion = $this->getMigrationManager()->getLastMigration();
        $this->setSchemaVersion($currentDbVersion);

        return $currentDbVersion;
    }

    /**
     * @param $fromVersion
     */
    public function schemaUpdate($fromVersion)
    {
        /**
         * @var DbMigrationManager $mgr
         */
        $mgr = Bootstrap::getContainer()->get('manager.db.migrations');
        $pool = $mgr->getMigrations($fromVersion);
        if (0 < count($pool)) {
            $prefix = $this->getWpdb()->base_prefix;
            foreach ($pool as $version => $migration) {
                /**
                 * @var SmartlingDbMigrationInterface $migration
                 */
                $this->logger->info('Starting applying migration ' . $migration->getVersion());
                $queries = $migration->getQueries($prefix);
                $stopMigrate = false;
                foreach ($queries as $query) {
                    $this->logger->debug('Executing query: ' . $query);
                    $result = $this->getWpdb()->query($query);
                    if (false === $result) {
                        $this->logger->error('Error executing query: ' . $this->getWpdb()->last_error);
                        $stopMigrate = true;
                        break;
                    }
                }
                if (false === $stopMigrate) {
                    $this->logger->info('Finished applying migration ' . $migration->getVersion());
                    $this->setSchemaVersion($migration->getVersion());
                } else {
                    $this->logger->error('Error occurred while applying migration ' . $migration->getVersion() .
                                         '. Error: ' . $this->getWpdb()->last_error);
                    break;
                }
            }
        } else {
            // Commented it because it generates too many noise in logs
            // $this->logger->info( 'Activated. No new migrations found.' );
        }
    }

    public function getSchemaVersion()
    {
        return (int)SimpleStorageHelper::get(self::SMARTLING_DB_SCHEMA_VERSION, 0);
    }

    /**
     * @param $version
     *
     * @return bool|null
     */
    public function setSchemaVersion($version)
    {
        $currentVersion = (int)$this->getSchemaVersion();
        $version = (int)$version;

        $result = SimpleStorageHelper::set(self::SMARTLING_DB_SCHEMA_VERSION, $version);

        $message = 'Smartling db schema update ';

        if (true === $result) {
            $message .= vsprintf('successfully completed from version %d to version %d.', [
                $currentVersion,
                $version,
            ]);

        } else {
            $message .= vsprintf('failed from version %d to version %d.', [
                $currentVersion,
                $version,
            ]);
        }
        $this->logger->info($message);

        return $result;
    }

    /**
     * Is executed on plugin deactivation
     */
    public function uninstall()
    {
        if (!defined('SMARTLING_COMPLETE_REMOVE'))
        {
            return;
        }

        foreach ($this->tables as $tableDefinition) {
            $table = $this->getTableName($tableDefinition);

            $this->logger->info('uninstalling tables', [$table]);
            $this->getWpdb()
                ->query('DROP TABLE IF EXISTS ' . $table);
        }
        delete_site_option(self::SMARTLING_DB_SCHEMA_VERSION);
    }

    public function activate()
    {
        $currentDbVersion = $this->getSchemaVersion();
        if (0 === $currentDbVersion) {
            $this->installDb();
        } else {
            $this->schemaUpdate($currentDbVersion);
        }
    }

    public function deactivate(){}

    /**
     * Extracts table name from tableDefinition
     *
     * @param array $tableDefinition
     *
     * @return string
     */
    private function getTableName(array $tableDefinition)
    {
        return $this->getWpdb()->base_prefix . $tableDefinition['name'];
    }

    /**
     * Extracts columns definition from tableDefinition
     *
     * @param array $tableDefinition
     *
     * @return array
     */
    private function getSchema(array $tableDefinition)
    {
        return $tableDefinition['columns'];
    }

    /**
     * Extracts primary key from tableDefinition
     *
     * @param array $tableDefinition
     *
     * @return array
     */
    private function getPrimaryKey(array $tableDefinition)
    {
        foreach ($tableDefinition['indexes'] as $indexDefinition) {
            if ($indexDefinition['type'] === 'primary') {
                return $indexDefinition['columns'];
            } else {
                continue;
            }
        }

        return [];
    }

    /**
     * Extracts indexes definitions from tableDefinition
     *
     * @param array $tableDefinition
     *
     * @return string
     */
    private function getIndex(array $tableDefinition)
    {
        $_indexes = [];

        foreach ($tableDefinition['indexes'] as $indexDefinition) {
            if ($indexDefinition['type'] === 'primary') {
                continue;
            } else {
                $_indexes[] = vsprintf(
                    '%s (%s)',
                    [
                        strtoupper($indexDefinition['type']),
                        '`' . implode('`, `', $indexDefinition['columns']) . '`',
                    ]
                );
            }
        }

        return implode(', ', $_indexes);
    }

    /**
     * Gets Character set and collation for table
     *
     * @return string
     */
    private function getCharsetCollate()
    {
        /** @noinspection IsEmptyFunctionUsageInspection */
        if (!empty($this->getWpdb()->charset) && false !== stripos($this->getWpdb()->charset, 'utf')) {
            $collate = 'DEFAULT CHARACTER SET ' . $this->getWpdb()->charset;
        } else {
            $collate = 'DEFAULT CHARACTER SET utf8';
        }

        /** @noinspection IsEmptyFunctionUsageInspection */
        if (!empty($this->getWpdb()->collate)) {
            $collate .= ' COLLATE ' . $this->getWpdb()->collate;
        }

        return $collate;
    }

    /**
     * @param array $columns
     *
     * @return string
     */
    private function arrayToSqlColumn(array $columns)
    {
        $out = '';
        foreach ($columns as $name => $type) {
            $out .= vsprintf('`%s` %s, ', [
                $name,
                $type,
            ]);
        }

        return $out;
    }

    /**
     * Builds SQL query to create a table from definition array
     *
     * @param $tableDefinition
     *
     * @return string
     */
    private function prepareSql(array $tableDefinition)
    {
        $table = $this->getTableName($tableDefinition);
        $pk = $this->getPrimaryKey($tableDefinition);
        $columns = $this->getSchema($tableDefinition);
        $schema = $this->arrayToSqlColumn($columns);
        $index = $this->getIndex($tableDefinition);
        $charset_collate = $this->getCharsetCollate();
        $add = '';

        if (!empty ($pk)) {
            $add .= vsprintf('PRIMARY KEY  (%s)', [implode(', ', $pk)]);
        }

        if (!empty ($index)) {
            $add .= vsprintf(', %s', [$index]);
        }

        $sql = vsprintf(
            'CREATE TABLE IF NOT EXISTS %s ( %s %s ) %s;',
            [
                $table,
                $schema,
                $add,
                $charset_collate,
            ]);

        return $sql;
    }

    /**
     * Escape string value
     *
     * @param string $string
     *
     * @return mixed
     */
    public function escape($string)
    {
        return $this->getWpdb()->_escape($string);
    }

    /**
     * @param string $tableName
     *
     * @return mixed
     */
    public function completeTableName($tableName)
    {
        return $this->getWpdb()->base_prefix . $tableName;
    }

    /**
     * @inheritdoc
     */
    public function query($query)
    {
        return $this->wpdb->query($query);
    }

    /**
     * @inheritdoc
     */
    public function fetch($query, $output = OBJECT)
    {
        return $this->getWpdb()->get_results($query, $output);
    }

    /**
     * @return integer
     */
    public function getLastInsertedId()
    {
        return $this->wpdb->insert_id;
    }

    /**
     * @return string
     */
    public function getLastErrorMessage()
    {
        return $this->getWpdb()->last_error;
    }

    /**
     * @inheritdoc
     */
    public function needRawSqlLog()
    {
        return $this->needSqlLog;
    }
}
