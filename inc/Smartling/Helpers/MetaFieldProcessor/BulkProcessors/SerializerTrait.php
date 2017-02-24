<?php

namespace Smartling\Helpers\MetaFieldProcessor\BulkProcessors;

/**
 * Class SerializerTrait
 * @package Smartling\Helpers\MetaFieldProcessor\BulkProcessors
 */
trait SerializerTrait
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @return SerializerInterface
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @param SerializerInterface $serializer
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
    }

}