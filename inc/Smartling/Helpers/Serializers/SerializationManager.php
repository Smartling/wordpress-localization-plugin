<?php

namespace Smartling\Helpers\Serializers;

use Smartling\Processors\SmartlingFactoryAbstract;

/**
 * Class SerializationManager
 * @package Smartling\Helpers\Serializers
 */
class SerializationManager extends SmartlingFactoryAbstract
{
    /**
     * ContentTypeManager constructor.
     */
    public function __construct()
    {
        parent::__construct();
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