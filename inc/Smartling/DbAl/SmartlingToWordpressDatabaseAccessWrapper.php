<?php

namespace Smartling\DbAl;


use Psr\Log\LoggerInterface;

class SmartlingToWordpressDatabaseAccessWrapper
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
    function query($query)
    {
        return $this->wpdb->query($query);
    }
}