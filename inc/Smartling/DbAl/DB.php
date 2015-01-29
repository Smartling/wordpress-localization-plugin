<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class DB implements SmartlingToCMSDatabaseAccessWrapper {

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
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {

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
			'name'    => SubmissionManager::SUBMISSIONS_TABLE_NAME,
			'columns' => SubmissionEntity::$fieldsDefinition,
			'indexes' => SubmissionEntity::$indexes,
		);
	}

	/**
	 * Is executed on plugin activation
	 */
	public function install () {
		foreach ( $this->tables as $tableDefinition ) {
			$query = $this->prepareSql( $tableDefinition );
			$this->logger->info( 'installing tables', array ( $query ) );
			$this->getWpdb()->query( $query );
		}
	}

	/**
	 * Is executed on plugin deactivation
	 */
	public function uninstall () {
		foreach ( $this->tables as $tableDefinition ) {
			$table = $this->getTableName( $tableDefinition );

			$this->logger->info( 'uninstalling tables', array ( $table ) );
			$this->getWpdb()->query( "DROP TABLE IF EXISTS " . $table );
		}
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
			if ( $indexDefinition['type'] == 'primary' ) {
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
			if ( $indexDefinition['type'] == 'primary' ) {
				continue;
			} else {
				$_indexes[] = vsprintf(
					"%s (%s)",
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
			$collate = "DEFAULT CHARACTER SET " . $this->getWpdb()->charset;
		} else {
			$collate = "DEFAULT CHARACTER SET utf8";
		}

		if ( ! empty( $this->getWpdb()->collate ) ) {
			$collate .= " COLLATE " . $this->getWpdb()->collate;
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
			$out .= "`{$name}` {$type}, ";
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
			$add .= vsprintf( "PRIMARY KEY  (%s)", array ( implode( ', ', $pk ) ) );
		}

		if ( ! empty ( $index ) ) {
			$add .= ", $index";
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
	public function fetch ( $query ) {
		return $this->getWpdb()->get_results( $query );
	}

	/**
	 * @return integer
	 */
	function getLastInsertedId () {
		return $this->wpdb->insert_id;
	}
}