<?php

namespace Smartling\DbAl;


use Psr\Log\LoggerInterface;

abstract class SmartlingToWordpressDatabaseAccessWrapper
    extends SmartlingToCMSDatabaseAccessWrapperAbstract
    implements SmartlingToCMSDatabaseAccessWrapper
{

    /**
     * @var \wpdb $wpdb
     */
    private $wpdb = null;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * @return \wpdb
     */
    public function getWpdb()
    {
        return $this->wpdb;
    }

    /**
     * Executes SQL query and returns the result
     * @param $query
     * @return mixed
     */
    public function query($query)
    {
        return $this->wpdb->query($query);
    }

    public function fetch($query)
    {
        return $this->getWpdb()->get_results($query);
    }
}