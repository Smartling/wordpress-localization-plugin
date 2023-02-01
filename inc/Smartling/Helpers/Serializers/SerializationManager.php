<?php

namespace Smartling\Helpers\Serializers;

use Smartling\Exception\SmartlingConfigException;
use Smartling\Processors\SmartlingFactoryAbstract;

class SerializationManager extends SmartlingFactoryAbstract
{
    /** @noinspection PhpUnused used in DI*/
    public function addSerializer(SerializerInterface $serializer)
    {
        $this->registerHandler($serializer->getType(), $serializer);
    }

    /** @noinspection PhpUnused */
    public function getSerializer(string $type): SerializerInterface
    {
        $result = $this->getHandler($type);
        if ($result instanceof SerializerInterface) {
            return $result;
        }

        throw new SmartlingConfigException(self::class . __METHOD__ . ' should return ' . SerializerInterface::class);
    }
}
