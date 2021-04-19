<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\Migrations\DbMigrationManager;
use Smartling\DbAl\Migrations\SmartlingDbMigrationInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Jobs\JobInformationEntity;
use Smartling\Jobs\SubmissionJobEntity;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Queue\Queue;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\WPInstallableInterface;

class DB implements SmartlingToCMSDatabaseAccessWrapperInterface, WPInstallableInterface
{

    const SMARTLING_DB_SCHEMA_VERSION = 'smartling_db_ver';

    /**
     * Plugin tables definition based on array
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

    /**
     * DB constructor.
     */
    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
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

    private function buildTableDefinitions(): void
    {
        $this->tables[] = [
            'columns' => JobInformationEntity::getFieldDefinitions(),
            'indexes' => JobInformationEntity::getIndexes(),
            'name' => JobInformationEntity::getTableName(),
        ];
        // Submissions
        $this->tables[] = [
            'name'    => SubmissionEntity::getTableName(),
            'columns' => SubmissionEntity::getFieldDefinitions(),
            'indexes' => SubmissionEntity::getIndexes(),
        ];
        $this->tables[] = [
            'columns' => SubmissionJobEntity::getFieldDefinitions(),
            'indexes' => SubmissionJobEntity::getIndexes(),
            'name' => SubmissionJobEntity::getTableName(),
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

    /**
     * Is executed on plugin activation
     */
    public function install()
    {
        $currentDbVersion = $this->getSchemaVersion();
        if (0 === $currentDbVersion) {
            $curVer = $currentDbVersion;

            $currentDbVersion = $this->installDb();

            // check if there was 1.0.12 version
            $this->getWpdb()
                ->query('SHOW TABLES LIKE \'%smartling%\'');
            $res = $this->getWpdb()->num_rows;

            if (0 < $res && 0 === $curVer) {
                // 1.0.12 detected
                $this->schemaUpdate($currentDbVersion);
            }
        } else {
            $this->schemaUpdate($currentDbVersion);
        }

    }

    private function installDb()
    {
        foreach ($this->tables as $tableDefinition) {
            $query = $this->prepareSql($tableDefinition);
            $this->logger->info(vsprintf('Installing table: %s', [$query]));
            $result = $this->getWpdb()->query($query);

            if (false === $result) {
                $message = vsprintf('Executing query |%s| has finished with error: %s', [$query,
                                                                                         $this->getWpdb()->last_error]);
                $this->logger->critical($message);
                throw new SmartlingDbException($message);

            }
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

                if (true === $stopMigrate) {
                    $message = vsprintf(
                        'Error applying migration <strong>#%s</strong>. <br/>
Got error: <strong>%s</strong>.<br/>
While executing query: <strong>%s</strong>. <br/>
Please download the log file (click <strong><a href="' . get_site_url() . '/wp-admin/admin-post.php?action=smartling_download_log_file">here</a></strong>) and contact <a href="mailto:support@smartling.com?subject=%s">support@smartling.com</a>.',
                        [
                            $migration->getVersion(),
                            $this->getWpdb()->last_error,
                            $query,
                            str_replace(' ', '%20', vsprintf('Wordpress Connector. Error applying migration %s', [$migration->getVersion()])),
                        ]
                    );

                    DiagnosticsHelper::addDiagnosticsMessage($message, true);

                    return;
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
        return IntegerParser::integerOrDefault(SimpleStorageHelper::get(self::SMARTLING_DB_SCHEMA_VERSION, 0), 0);
    }

    /**
     * @param $version
     *
     * @return bool|null
     */
    public function setSchemaVersion($version)
    {
        $currentVersion = $this->getSchemaVersion();
        $version = IntegerParser::integerOrDefault($version, 0);

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
        if (!defined('SMARTLING_COMPLETE_REMOVE')) {
            return;
        }

        foreach ($this->tables as $tableDefinition) {
            $table = $this->getTableName($tableDefinition);

            $this->logger->info('uninstalling tables', [$table]);
            $this->getWpdb()->query('DROP TABLE IF EXISTS ' . $table);
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

    public function deactivate()
    {
    }

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
            }

            $_indexes[] = vsprintf(
                '%s (%s)',
                [
                    strtoupper($indexDefinition['type']),
                    '`' . implode('`, `', $indexDefinition['columns']) . '`',
                ]
            );
        }

        return implode(', ', $_indexes);
    }

    private function getCharset()
    {
        return $this->getWpdb()->charset;
    }

    private function getCollate()
    {
        return $this->getWpdb()->collate;
    }

    /**
     * Gets Character set and collation for table
     * @return string
     */
    private function getCharsetCollate()
    {
        $parts = [];

        $charset = $this->getCharset();

        if (!empty($charset)) {
            $parts['charset'] = vsprintf('DEFAULT CHARACTER SET %s', [$charset]);
        }

        $collate = $this->getCollate();

        if (!empty($collate)) {
            $parts['collate'] = vsprintf('COLLATE %s', [$collate]);
        }

        if (0 < count($parts)) {
            return vsprintf(' %s ', [implode(' ', $parts)]);
        } else {
            return '';
        }
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
     */
    public function prepareSql(array $tableDefinition): string
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

    public function escape(string $string): string
    {
        return $this->getWpdb()->_escape($string);
    }

    public function completeTableName(string $tableName): string
    {
        return $this->getWpdb()->base_prefix . $tableName;
    }

    public function completeMultisiteTableName(string $tableName): string
    {
        return $this->getWpdb()->prefix . $tableName;
    }

    public function query(string $query)
    {
        return $this->wpdb->query($query);
    }

    public function fetch(string $query, string $output = OBJECT)
    {
        return $this->getWpdb()->get_results($query, $output);
    }

    public function getLastInsertedId(): int
    {
        return $this->wpdb->insert_id;
    }

    public function getLastErrorMessage(): string
    {
        return $this->getWpdb()->last_error;
    }
}
