<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 21:46
 */

namespace Smartling\DbAl;


use Psr\Log\LoggerInterface;

class DB extends SmartlingToWordpressDatabaseAccessWrapper {
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    public function install() {
        $query = $this->prepareSql();
        dbDelta($query);
    }

    public function uninstall() {
        $this->getWpdb()->query("DROP TABLE IF EXISTS " . $this->getTableName());
    }
    private function getTableName() {

        return $this->getWpdb()->base_prefix . 'smartling_translation';
    }

    private function getSchema() {
        return array(
            'id'             => 'INT NOT NULL AUTO_INCREMENT',
            'post_id'        => 'INT NOT NULL',
            'name'           => 'varchar(255) NOT NULL',
            'type'           => 'varchar(255)',
            'locale'         => 'varchar(30) NOT NULL',
            'status'         => 'varchar(20)',
            'progress'       => 'TINYINT NOT NULL DEFAULT 0',
            'submittedAt' => 'TIMESTAMP',
            'submitter'      => 'varchar(30) NOT NULL',
            'appliedAt'   => 'DATETIME',
            'applier'        => 'varchar(30)'
        );
    }

    private function getPrimaryKey() {

        return 'id';
    }


    private function getIndex() {

        return 'INDEX ( `post_id` )';
    }

    public function getCharsetCollate() {
        if ( !empty( $this->getWpdb()->charset )
            && FALSE !== stripos( $this->getWpdb()->charset, 'utf')) {
            $collate = "DEFAULT CHARACTER SET " . $this->getWpdb()->charset;
        } else {
            $collate = "DEFAULT CHARACTER SET utf8";
        }

        if ( ! empty( $this->getWpdb()->collate ) ) {
            $collate .= " COLLATE " . $this->getWpdb()->collate;
        }
        return $collate;
    }

    public function arrayToSqlColumn( Array $array ) {
        $out = '';
        foreach ( $array as $key => $properties ) {
            $out .= "{$key} {$properties},\n";
        }
        return $out;
    }

    public function prepareSql() {

        $table           = $this->getTableName();
        $pk              = $this->getPrimaryKey();
        $columns         = $this->getSchema();
        $schema          = $this->arrayToSqlColumn($columns);
        $index           = $this->getIndex();
        $charset_collate = $this->getCharsetCollate();
        $add             = '';

        if ( !empty ( $pk ) ) {
            $add .= "PRIMARY KEY  ($pk)"; // two spaces!
        }

        if ( !empty ( $index ) ) {
            $add .= ", $index";
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' ( ' . $schema . ' ' . $add . ' ) ' . $charset_collate . ';';

        return $sql;
    }
}