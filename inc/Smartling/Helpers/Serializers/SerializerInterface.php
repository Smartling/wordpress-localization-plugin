<?php

namespace Smartling\Helpers\Serializers;

/**
 * Interface SerializerInterface
 * @package Smartling\Helpers\Serializers
 */
interface SerializerInterface
{
    /**
     * @return string
     */
    public function getType();

    /**
     * @param array $data
     *
     * @return string
     */
    public function serialize($data);

    /**
     * @param string $string
     *
     * @return array
     */
    public function unserialize($string);
}