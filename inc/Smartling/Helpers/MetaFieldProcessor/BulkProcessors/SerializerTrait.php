<?php

namespace Smartling\Helpers\MetaFieldProcessor\BulkProcessors;

use Smartling\Helpers\Serializers\SerializerInterface;

trait SerializerTrait
{
    private SerializerInterface $serializer;

    public function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }
}
