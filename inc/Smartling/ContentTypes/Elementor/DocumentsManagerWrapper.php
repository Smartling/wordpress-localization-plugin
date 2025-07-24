<?php

namespace Smartling\ContentTypes\Elementor;

use Elementor\Core\Documents_Manager;

class DocumentsManagerWrapper extends Documents_Manager
{
    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(private Documents_Manager $documentsManager) {
        // Parent constructor not called intentionally
    }

    public function getManagerWithoutDocuments(): Documents_Manager
    {
        $this->documentsManager->documents = [];
        return $this->documentsManager;
    }
}
