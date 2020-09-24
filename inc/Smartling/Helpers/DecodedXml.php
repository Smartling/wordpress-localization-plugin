<?php

namespace Smartling\Helpers;

class DecodedXml
{
    private $fields;
    private $sourceFields;

    public function __construct(array $fields, array $sourceFields)
    {
        $this->fields = $fields;
        $this->sourceFields = $sourceFields;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return array
     */
    public function getSourceFields()
    {
        return $this->sourceFields;
    }
}
