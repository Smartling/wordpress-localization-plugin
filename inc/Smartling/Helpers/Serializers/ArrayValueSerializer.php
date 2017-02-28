<?php

namespace Smartling\Helpers\Serializers;

/**
 * Class ArrayValueSerializer
 * @package Smartling\Helpers\Serializers
 * This serializer is applied when set of values should not be converted in any state, is is already an array.
 */
class ArrayValueSerializer implements SerializerInterface
{

    /**
     * @return string
     */
    public function getType()
    {
        return 'array-value';
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public function serialize($data)
    {
        return $data;
    }

    /**
     * @param string $string
     *
     * @return array
     */
    public function unserialize($string)
    {
        return $string;
    }
}