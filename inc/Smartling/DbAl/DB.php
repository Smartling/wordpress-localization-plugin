<?php

namespace Smartling\DbAl;

use Smartling\Bootstrap;
use Smartling\DbAl\Migrations\DbMigrationManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\SubmissionJobEntity;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Queue\Queue;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Psr\Log\LoggerInterface;
use Smartling\WP\WPInstallableInterface;

class DB implements SmartlingToCMSDatabaseAccessWrapperInterface, WPInstallableInterface
{
    private const SMARTLING_DB_SCHEMA_VERSION = 'smartling_db_ver';

    /**
     * Plugin tables definition based on array
     * @var array
     */
    private array $tables = [];

    /**
     * @var \wpdb
     */
    private $wpdb;

    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->buildTableDefinitions();
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function getMigrationManager(): DbMigrationManager
    {
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
            'columns' => JobEntity::getFieldDefinitions(),
            'indexes' => JobEntity::getIndexes(),
            'name' => JobEntity::getTableName(),
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
    public function install(): void
    {
        $currentDbVersion = $this->getSchemaVersion();
        if (0 === $currentDbVersion) {
            $currentDbVersion = $this->installDb();

            // check if there was 1.0.12 version
            $this->getWpdb()
                ->query('SHOW TABLES LIKE \'%smartling%\'');
            $res = $this->getWpdb()->num_rows;

            if (0 < $res) {
                // 1.0.12 detected
                $this->schemaUpdate($currentDbVersion);
            }
        } else {
            $this->schemaUpdate($currentDbVersion);
        }
    }

    private function installDb(): int
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

    public function schemaUpdate(int $fromVersion): void
    {
        $prefix = $this->getWpdb()->base_prefix;
        foreach ($this->getMigrationManager()->getMigrations($fromVersion) as $migration) {
            $this->logger->info('Starting applying migration ' . $migration->getVersion());
            $queries = $migration->getQueries($prefix);
            foreach ($queries as $query) {
                $this->logger->debug('Executing query: ' . $query);
                $result = $this->getWpdb()->query($query);
                if (false === $result) {
                    $this->logger->error('Error executing query: ' . $this->getWpdb()->last_error);
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
            }

            $this->logger->info('Finished applying migration ' . $migration->getVersion());
            $this->setSchemaVersion($migration->getVersion());
        }
    }

    public function getSchemaVersion(): int
    {
        return IntegerParser::integerOrDefault(SimpleStorageHelper::get(self::SMARTLING_DB_SCHEMA_VERSION, 0), 0);
    }

    public function setSchemaVersion(int $version): bool
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
    public function uninstall(): void
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

    public function activate(): void
    {
        $currentDbVersion = $this->getSchemaVersion();
        if (0 === $currentDbVersion) {
            $this->installDb();
        } else {
            $this->schemaUpdate($currentDbVersion);
        }
    }

    public function deactivate(): void
    {
    }

    /**
     * Extracts table name from tableDefinition
     */
    private function getTableName(array $tableDefinition): string
    {
        return $this->getWpdb()->base_prefix . $tableDefinition['name'];
    }

    /**
     * Extracts columns definition from tableDefinition
     */
    private function getSchema(array $tableDefinition): array
    {
        return $tableDefinition['columns'];
    }

    /**
     * Extracts primary key from tableDefinition
     */
    private function getPrimaryKey(array $tableDefinition): array
    {
        foreach ($tableDefinition['indexes'] as $indexDefinition) {
            if ($indexDefinition['type'] === 'primary') {
                return $indexDefinition['columns'];
            }
        }

        return [];
    }

    /**
     * Extracts indexes definitions from tableDefinition
     */
    private function getIndex(array $tableDefinition): string
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

    private function getCharset(): string
    {
        return $this->getWpdb()->charset;
    }

    private function getCollate(): string
    {
        return $this->getWpdb()->collate;
    }

    /**
     * Gets Character set and collation for table
     */
    private function getCharsetCollate(): string
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
        }

        return '';
    }

    private function arrayToSqlColumn(array $columns): string
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

        return "CREATE TABLE IF NOT EXISTS $table ( $schema $add ) $charset_collate;";
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

    private function loggedQuery(string $query): int|bool
    {
        $result = $this->wpdb->query($query);
        if ($result === false) {
            $this->logFailedQuery($query);
        }

        return $result;
    }

    private function logFailedQuery(string $query): void
    {
        $this->logger->notice("Query failed: $query, last error: {$this->getLastErrorMessage()}");
    }

    public function query(string $query): int|bool
    {
        return $this->loggedQuery($query);
    }

    public function queryPrepared(string $query, ...$args): bool|int
    {
        return $this->loggedQuery($this->prepare($query, ...$args));
    }

    public function fetch(string $query, string $output = OBJECT): array|object|null
    {
        $results = $this->getWpdb()->get_results($query, $output);
        if ($results === null) {
            $this->logFailedQuery($query);
        }

        return $results;
    }

    public function getResultsArray(string $query): ?array
    {
        $results = $this->getWpdb()->get_results($query, ARRAY_A);
        if ($results === null) {
            $this->logFailedQuery($query);
        }

        return $results;
    }

    public function getRowArray(string $query, int $index = 0): ?array
    {
        $result = $this->getWpdb()->get_row($query, ARRAY_A, $index);
        if ($result === null) {
            $this->logFailedQuery($query);
        }

        return $result;
    }

    public function fetchPrepared(string $query, ...$args): array
    {
        $result = $this->fetch($this->prepare($query, ...$args), ARRAY_A);
        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    public function getLastInsertedId(): int
    {
        return $this->wpdb->insert_id;
    }

    public function getLastErrorMessage(): string
    {
        return $this->getWpdb()->last_error;
    }

    private function prepare(string $query, ...$args): string
    {
        return $this->wpdb->prepare($query, ...$args);
    }

    /**
     * @return string the prefix without the blog number appended
     */
    public function getBasePrefix(): string
    {
        return $this->wpdb->base_prefix;
    }

    /**
     * @return string the assigned WordPress table prefix for the blog
     */
    public function getPrefix(): string
    {
        return $this->wpdb->prefix;
    }
}
