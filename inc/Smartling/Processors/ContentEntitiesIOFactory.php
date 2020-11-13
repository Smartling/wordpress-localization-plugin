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
     * @param string $contentType
     * @param EntityAbstract $mapper
     * @param bool $force
     */
    public function registerMapper($contentType, $mapper, $force = false)
    {
        $this->getLogger()->debug('called registerMapper with ' . get_class($mapper));
        $this->registerHandler($contentType, $mapper, $force);
    }

    /**
     * @param $contentType
     *
     * @return EntityAbstract
     * @throws SmartlingInvalidFactoryArgumentException
     */
    public function getMapper($contentType)
    {
        $obj = $this->getHandler($contentType);

        if ($obj instanceof EntityAbstract) {
            return clone $obj;
        }

        Bootstrap::DebugPrint([$contentType, $obj],true);
    }
}