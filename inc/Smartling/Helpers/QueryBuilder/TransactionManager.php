<?php

namespace Smartling\Helpers\QueryBuilder;

use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface as DBInterface;

/**
 * Class TransactionManager
 * @package Smartling\Helpers\QueryBuilder
 */
class TransactionManager
{

    /**
     * @var DBInterface
     */
    private $db;

    /**
     * TransactionManager constructor.
     *
     * @param DBInterface $dbal
     */
    public function __construct(DBInterface $dbal)
    {
        $this->db = $dbal;
    }

    public function getAutocommit()
    {
        $result = $this->db->fetch('SELECT @@autocommit as autocommit', \ARRAY_A);

        return (int)$result[0]['autocommit'];
    }

    public function setAutocommit($value)
    {
        $this->db->query(vsprintf('SET autocommit=%s', [(int)$value]));
    }

    public function transactionStart()
    {
        $this->db->query('START TRANSACTION');
    }

    public function transactionCommit()
    {
        $this->db->query('COMMIT');
    }

    /**
     * Returns null if rows are locked
     *
     * @param $query
     *
     * @return mixed|null
     */
    public function executeSelectForUpdate($query)
    {
        $query_f = vsprintf('%s FOR UPDATE', [$query]);
        $result = $this->db->fetch($query_f, \ARRAY_A);
        $err = $this->db->getLastErrorMessage();

        return '' === $err ? $result : null;
    }
}