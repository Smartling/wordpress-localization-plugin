<?php

namespace Smartling\Helpers;

Abstract class HelperAbstract {

    private $_logger = null;

    public function __construct($logger) {
        $this->_logger = $logger;
    }

    protected function logError($message)
    {
        $this->_logger->error($message);
    }


}