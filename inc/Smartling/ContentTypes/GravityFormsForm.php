<?php

namespace Smartling\ContentTypes;

class GravityFormsForm extends ContentTypeAbstract {
    public function __construct()
    {
    }

    public function getSystemName(): string
    {
        return ExternalContentGravityForms::CONTENT_TYPE;
    }

    public function getLabel(): string
    {
        return 'Gravity Forms Form';
    }

    public function getBaseType(): string
    {
        return ContentTypeManager::VIRTUAL;
    }

    public function isVirtual(): bool
    {
        return true;
    }
}
