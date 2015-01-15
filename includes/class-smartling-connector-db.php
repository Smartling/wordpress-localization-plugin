<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Plugin_Name
 * @subpackage Plugin_Name/includes
 * @author     Your Name <email@example.com>
 */


/**
* 
*/
class Smartling_Connector_DB
{
	
	public $db;

	public static $query;


	// function __construct() {

	// 	$this->db = $GLOBALS['wpdb'];
	// 	$this->prepare_sql();
	// }


	public function array_to_sql_columns( Array $array ) {

		$out = '';

		foreach ( $array as $key => $properties )
			$out .= "$key $properties,\n";

		return $out;
	}


	public function get_table_name() {

		return $this->db->base_prefix . 'smartling_translation';
	}


	public function get_schema() {

		return array(
			'st_id'             => 'INT NOT NULL AUTO_INCREMENT',
			'st_post_id'        => 'INT NOT NULL',
			'st_name'           => 'varchar(255) NOT NULL',
			'st_type'           => 'varchar(255)',
			'st_locale'         => 'varchar(30) NOT NULL',
			'st_status'         => 'varchar(20)',
			'st_progress'       => 'TINYINT NOT NULL DEFAULT 0',
			'st_time_submitted' => 'TIMESTAMP',
			'st_submitter'      => 'varchar(30) NOT NULL',
			'st_time_applied'   => 'DATETIME',
			'st_applier'        => 'varchar(30)',
			);
	}


	public function get_primary_key() {

		return 'st_id';
	}


	public function get_index() {

		return 'INDEX ( `st_post_id` )';
	}


	public function get_charset_collate() {

		if ( ! empty( $this->db->charset ) && FALSE !== stripos( $this->db->charset, 'utf') )

			$charset_collate = "DEFAULT CHARACTER SET " . $this->db->charset;

		else 
			$charset_collate = "DEFAULT CHARACTER SET utf8";
		

		if ( ! empty( $this->db->collate ) )

			$charset_collate .= " COLLATE " . $this->db->collate;
		

		return $charset_collate;

	}


	public function prepare_sql() {

		$table           = $this->get_table_name();
		$pk              = $this->get_primary_key();
		$columns         = $this->get_schema();
		$schema          = $this->array_to_sql_columns($columns);
		$index           = $this->get_index();
		$charset_collate = $this->get_charset_collate();
		$add             = '';

		if ( ! empty ( $pk ) )
			$add .= "PRIMARY KEY  ($pk)"; // two spaces!

		if ( ! empty ( $index ) )
			$add .= ", $index";

		$sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' ( ' . $schema . ' ' . $add . ' ) ' . $charset_collate . ';';

		// return $sql;
		self::$query = $sql;
		
	}


	public static function install() {

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::$query );

		#print_r( $this->db->show_errors() );
	}

}