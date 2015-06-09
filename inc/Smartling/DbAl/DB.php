<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\Migrations\DbMigrationManager;
use Smartling\DbAl\Migrations\SmartlingDbMigrationInterface;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class DB
 *
 * @package Smartling\DbAl
 */
class DB implements SmartlingToCMSDatabaseAccessWrapperInterface {

	const SMARTLING_DB_SCHEMA_VERSION = 'smartling_db_ver';

	/**
	 * Plugin tables definition based on array
	 *
	 * @var array
	 */
	private $tables = array ();

	/**
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {

		$this->logger = $logger;

		$this->buildTableDefinitions();

		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * @return \wpdb
	 */
	public function getWpdb () {
		return $this->wpdb;
	}

	private function buildTableDefinitions () {
		// Submissions
		$this->tables[] = array (
			'name'    => SubmissionEntity::getTableName(),
			'columns' => SubmissionEntity::getFieldDefinitions(),
			'indexes' => SubmissionEntity::getIndexes(),
		);
		// Configuration profiles
		$this->tables[] = array (
			'name'    => ConfigurationProfileEntity::getTableName(),
			'columns' => ConfigurationProfileEntity::getFieldDefinitions(),
			'indexes' => ConfigurationProfileEntity::getIndexes(),
		);
	}

	/**
	 * Is executed on plugin activation
	 */
	public function install () {
		$currentDbVersion = $this->getSchemaVersion();
		if ( 0 === $currentDbVersion ) {
			// check if there was 1.0.12 version
			$this->getWpdb()->query( 'SHOW TABLES LIKE \'%smartling%\'' );
			$res = $this->getWpdb()->num_rows;
			if ( 0 < $res ) {
				// 1.0.12 detected
				$currentDbVersion ++;
				$this->setSchemaVersion( $currentDbVersion );
				$this->schemaUpdate( $currentDbVersion );
			} else {
				foreach ( $this->tables as $tableDefinition ) {
					$query = $this->prepareSql( $tableDefinition );
					$this->logger->info( vsprintf( 'Installing tables: %s', array ( $query ) ) );
					$this->getWpdb()->query( $query );
				}
				$currentDbVersion ++;
				$this->setSchemaVersion( $currentDbVersion );
			}
		} else {
			$this->schemaUpdate( $currentDbVersion );
		}

	}

	public function schemaUpdate ( $fromVersion ) {
		/**
		 * @var DbMigrationManager $mgr
		 */
		$mgr  = Bootstrap::getContainer()->get( 'manager.db.migrations' );
		$pool = $mgr->getMigrations( $fromVersion );
		if ( 0 < count( $pool ) ) {
			$prefix = $this->getWpdb()->base_prefix;
			foreach ( $pool as $version => $migration ) {
				/**
				 * @var SmartlingDbMigrationInterface $migration
				 */
				$this->logger->info( 'Starting applying migration ' . $migration->getVersion() );
				$queries     = $migration->getQueries( $prefix );
				$stopMigrate = false;
				foreach ( $queries as $query ) {
					$this->logger->debug( 'Executing query: ' . $query );
					$result = $this->getWpdb()->query( $query );
					if ( false === $result ) {
						$this->logger->error( 'Error executing query: ' . $this->getWpdb()->last_error );
						$stopMigrate = true;
						break;
					}
				}
				if ( false === $stopMigrate ) {
					$this->logger->info( 'Finished applying migration ' . $migration->getVersion() );
					$this->setSchemaVersion( $migration->getVersion() );
				} else {
					$this->logger->error( 'Error occurred while applying migration ' . $migration->getVersion() . '. Error: ' . $this->getWpdb()->last_error );
					break;
				}
			}
		} else {
			$this->logger->info( 'Activated. No new migrations found.' );
		}
	}

	public function getSchemaVersion () {
		return (int) get_site_option( self::SMARTLING_DB_SCHEMA_VERSION, 0, false );
	}

	/**
	 * @param $version
	 *
	 * @return bool|null
	 */
	public function setSchemaVersion ( $version ) {
		$currentVersion = $this->getSchemaVersion();
		$version        = (int) $version;
		$result         = null;
		$message        = 'Smartling db schema update ';
		if ( false === get_site_option( self::SMARTLING_DB_SCHEMA_VERSION, false, false ) ) {
			$result = add_site_option( self::SMARTLING_DB_SCHEMA_VERSION, $version );
		} else {
			$result = update_site_option( self::SMARTLING_DB_SCHEMA_VERSION, $version );
		}

		if ( true === $result ) {
			$message .= vsprintf( 'successfully completed from version %d to version %d.',
				array ( $currentVersion, $version ) );

		} else {
			$message .= vsprintf( 'failed from version %d to version %d.', array ( $currentVersion, $version ) );
		}
		$this->logger->info( $message );

		return $result;
	}

	/**
	 * Is executed on plugin deactivation
	 */
	public function uninstall () {
		foreach ( $this->tables as $tableDefinition ) {
			$table = $this->getTableName( $tableDefinition );

			$this->logger->info( 'uninstalling tables', array ( $table ) );
			$this->getWpdb()->query( 'DROP TABLE IF EXISTS ' . $table );
		}
		delete_site_option(self::SMARTLING_DB_SCHEMA_VERSION);
	}

	/**
	 * Extracts table name from tableDefinition
	 *
	 * @param array $tableDefinition
	 *
	 * @return string
	 */
	private function getTableName ( array $tableDefinition ) {
		return $this->getWpdb()->base_prefix . $tableDefinition['name'];
	}

	/**
	 * Extracts columns definition from tableDefinition
	 *
	 * @param array $tableDefinition
	 *
	 * @return array
	 */
	private function getSchema ( array $tableDefinition ) {
		return $tableDefinition['columns'];
	}

	/**
	 * Extracts primary key from tableDefinition
	 *
	 * @param array $tableDefinition
	 *
	 * @return array
	 */
	private function getPrimaryKey ( array $tableDefinition ) {
		foreach ( $tableDefinition['indexes'] as $indexDefinition ) {
			if ( $indexDefinition['type'] === 'primary' ) {
				return $indexDefinition['columns'];
			} else {
				continue;
			}
		}

		return array ();
	}

	/**
	 * Extracts indexes definitions from tableDefinition
	 *
	 * @param array $tableDefinition
	 *
	 * @return string
	 */
	private function getIndex ( array $tableDefinition ) {
		$_indexes = array ();

		foreach ( $tableDefinition['indexes'] as $indexDefinition ) {
			if ( $indexDefinition['type'] === 'primary' ) {
				continue;
			} else {
				$_indexes[] = vsprintf(
					'%s (%s)',
					array (
						strtoupper( $indexDefinition['type'] ),
						'`' . implode( '`, `', $indexDefinition['columns'] ) . '`'
					)
				);
			}
		}

		return implode( ', ', $_indexes );
	}

	/**
	 * Gets Character set and collation for table
	 *
	 * @return string
	 */
	private function getCharsetCollate () {
		if ( ! empty( $this->getWpdb()->charset )
		     && false !== stripos( $this->getWpdb()->charset, 'utf' )
		) {
			$collate = 'DEFAULT CHARACTER SET ' . $this->getWpdb()->charset;
		} else {
			$collate = 'DEFAULT CHARACTER SET utf8';
		}

		if ( ! empty( $this->getWpdb()->collate ) ) {
			$collate .= ' COLLATE ' . $this->getWpdb()->collate;
		}

		return $collate;
	}

	/**
	 * @param array $columns
	 *
	 * @return string
	 */
	private function arrayToSqlColumn ( array $columns ) {
		$out = '';
		foreach ( $columns as $name => $type ) {
			$out .= vsprintf( '`%s` %s, ', array ( $name, $type ) );
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
	private function prepareSql ( array $tableDefinition ) {

		$table           = $this->getTableName( $tableDefinition );
		$pk              = $this->getPrimaryKey( $tableDefinition );
		$columns         = $this->getSchema( $tableDefinition );
		$schema          = $this->arrayToSqlColumn( $columns );
		$index           = $this->getIndex( $tableDefinition );
		$charset_collate = $this->getCharsetCollate();
		$add             = '';

		if ( ! empty ( $pk ) ) {
			$add .= vsprintf( 'PRIMARY KEY  (%s)', array ( implode( ', ', $pk ) ) );
		}

		if ( ! empty ( $index ) ) {
			$add .= vsprintf( ', %s', array ( $index ) );
		}

		$sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' ( ' . $schema . ' ' . $add . ' ) ' . $charset_collate . ';';

		return $sql;
	}

	/**
	 * Escape string value
	 *
	 * @param string $string
	 *
	 * @return mixed
	 */
	function escape ( $string ) {
		return $this->getWpdb()->_escape( $string );
	}

	/**
	 * @param string $tableName
	 *
	 * @return mixed
	 */
	function completeTableName ( $tableName ) {
		return $this->getWpdb()->base_prefix . $tableName;
	}


	/**
	 * @inheritdoc
	 */
	public function query ( $query ) {
		return $this->wpdb->query( $query );
	}

	/**
	 * @inheritdoc
	 */
	public function fetch ( $query, $output = OBJECT ) {
		return $this->getWpdb()->get_results( $query, $output );
	}

	/**
	 * @return integer
	 */
	function getLastInsertedId () {
		return $this->wpdb->insert_id;
	}

	/**
	 * @return string
	 */
	function getLastErrorMessage () {
		return $this->getWpdb()->last_error;
	}
}