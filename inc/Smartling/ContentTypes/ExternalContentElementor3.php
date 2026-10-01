<?php

namespace Smartling\ContentTypes;

class ExternalContentElementor3 extends ExternalContentElementorAbstract
{
    public function getMaxVersion(): string
    {
        return '3';
    }

    public function getMinVersion(): string
    {
        return '3';
    }

    /**
     * Name used in logs to tell the handler apart from ExternalContentElementor4, the data key stays the plugin id
     */
    public function getLogName(): string
    {
        return 'elementor3';
    }
}
