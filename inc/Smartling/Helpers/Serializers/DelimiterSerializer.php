<?php

namespace Smartling\Helpers\Serializers;

/**
 * Class DelimiterSerializer
 * @package Smartling\Helpers\Serializers
 */
class DelimiterSerializer implements SerializerInterface
{
    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var string
     */
    private $type;

    /**
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * @param string $delimiter
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }


    /**
     * @param array $data
     *
     * @return string
     */
    public function serialize($data)
    {
        return implode($this->getDelimiter(), $data);
    }

    /**
     * @param string $string
     *
     * @return array
     */
    public function unserialize($string)
    {
        return explode($this->getDelimiter(), $string);
    }
}