<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

/**
 * Class SmartlingToWordpressDatabaseAccessWrapper
 * @package Smartling\DbAl
 */
abstract class SmartlingToWordpressDatabaseAccessWrapper
    extends SmartlingToCMSDatabaseAccessWrapperAbstract
    implements SmartlingToCMSDatabaseAccessWrapper
{

    /**
     * @var \wpdb
     */
    private $wpdb = null;

    /**
     * @param LoggerInterface $logger
     */
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
     * @inheritdoc
     */
    public function query($query)
    {
        return $this->wpdb->query($query);
    }

    /**
     * @inheritdoc
     */
    public function fetch($query)
    {
        return $this->getWpdb()->get_results($query);
    }
}