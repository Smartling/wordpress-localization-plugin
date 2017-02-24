<?php

namespace Smartling\Helpers\Serializers;

/**
 * Class DummySerializer
 * @package Smartling\Helpers\Serializers
 */
class DummySerializer implements SerializerInterface
{

    /**
     * @return string
     */
    public function getType()
    {
        return 'none';
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public function serialize($data)
    {
        return reset($data);
    }

    /**
     * @param string $string
     *
     * @return array
     */
    public function unserialize($string)
    {
        return [$string];
    }
}