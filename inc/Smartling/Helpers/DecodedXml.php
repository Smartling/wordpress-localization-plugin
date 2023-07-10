<?php

namespace Smartling\Helpers;

class DecodedXml
{
    private array $originalFields;
    private array $translatedFields;

    public function __construct(array $translatedFields, array $originalFields)
    {
        $this->originalFields = $originalFields;
        $this->translatedFields = $translatedFields;
    }

    public function getOriginalFields(): array
    {
        return $this->originalFields;
    }

    public function getTranslatedFields(): array
    {
        return $this->translatedFields;
    }
}
