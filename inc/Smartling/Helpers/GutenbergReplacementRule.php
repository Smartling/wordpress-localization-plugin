<?php

namespace Smartling\Helpers;

class GutenbergReplacementRule
{
    private $property;
    private $type;

    /**
     * @param string $type
     * @param $property
     */
    public function __construct($type, $property)
    {
        if (!is_string($property)) {
            throw new \InvalidArgumentException('Property expected to be string');
        }
        if (!is_string($type)) {
            throw new \InvalidArgumentException('Type expected to be string');
        }
        $this->property = $property;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
