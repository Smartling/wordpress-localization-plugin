<?php

namespace Smartling\Processors;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\MonologWrapper\MonologWrapper;

/**
 * Class ContentEntitiesIOFactory
 *
 * @package Smartling\Processors
 */
class ContentEntitiesIOFactory extends SmartlingFactoryAbstract
{

    /**
     * ContentEntitiesIOFactory constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->message = 'Requested entity wrapper for content-type \'%s\' is not registered. Called by: %s';
    }

    /**
     * @param                $contentType
     * @param EntityAbstract $mapper
     * @param bool           $force
     */
    public function registerMapper($contentType, $mapper, $force = false)
    {
        parent::registerHandler($contentType, $mapper, $force);
    }

    /**
     * @param $contentType
     *
     * @return EntityAbstract
     * @throws SmartlingInvalidFactoryArgumentException
     */
    public function getMapper($contentType)
    {
        $obj = parent::getHandler($contentType);

        if (is_object($obj)){
            return clone $obj;
        } else {
            Bootstrap::DebugPrint([$contentType,$obj],true);
        }

    }
}