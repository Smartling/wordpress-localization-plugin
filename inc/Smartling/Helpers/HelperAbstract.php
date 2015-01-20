<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;

Abstract class HelperAbstract {

    private $_logger = null;

    public function __construct(LoggerInterface $logger) {
        $this->_logger = $logger;
    }

    protected function logError($message)
    {
        $this->_logger->error($message);
    }


}