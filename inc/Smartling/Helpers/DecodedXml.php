<?php

namespace Smartling\Helpers;

class DecodedXml
{
    private $originalFields;
    private $translatedFields;

    public function __construct(array $translatedFields, array $originalFields)
    {
        $this->originalFields = $originalFields;
        $this->translatedFields = $translatedFields;
    }

    /**
     * @return array
     */
    public function getOriginalFields()
    {
        return $this->originalFields;
    }

    /**
     * @return array
     */
    public function getTranslatedFields()
    {
        return $this->translatedFields;
    }
}
