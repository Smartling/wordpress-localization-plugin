<?php

namespace Smartling\Helpers\Serializers;

use Psr\Log\LoggerInterface;
use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class SerializationManager
 * @package Smartling\Helpers\Serializers
 */
class SerializationManager extends SmartlingFactoryAbstract
{
    /**
     * ContentTypeManager constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setAllowDefault(false);
    }

    /**
     * @param SerializerInterface $serializer
     */
    public function addSerializer(SerializerInterface $serializer)
    {
        $this->registerHandler($serializer->getType(), $serializer);
    }

    /**
     * @param $type
     *
     * @return object
     */
    public function getSerializer($type)
    {
        return $this->getHandler($type);
    }
}