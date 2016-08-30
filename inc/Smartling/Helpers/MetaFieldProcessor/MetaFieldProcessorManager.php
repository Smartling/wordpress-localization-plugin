<?php

namespace Smartling\Helpers\MetaFieldProcessor;

use Psr\Log\LoggerInterface;
use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class MetaFieldProcessorManager
 * @package Smartling\Helpers\MetaFieldProcessor
 */
class MetaFieldProcessorManager extends SmartlingFactoryAbstract
{
    /**
     * MetaFieldProcessorManager constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setAllowDefault(true);
    }

    /**
     * @param MetaFieldProcessorInterface $handler
     */
    public function register(MetaFieldProcessorInterface $handler)
    {
        parent::registerHandler($handler->getFieldName(), $handler);
    }

    /**
     * @param $fieldName
     *
     * @return object
     */
    public function getProcessor($fieldName)
    {
        return clone parent::getHandler($fieldName);
    }
}