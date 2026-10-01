<?php

namespace Smartling\ContentTypes;

class ExternalContentElementor4 extends ExternalContentElementorAbstract
{
    public function getMaxVersion(): string
    {
        return '4';
    }

    public function getMinVersion(): string
    {
        return '4';
    }

    /**
     * Name used in logs to tell the handler apart from ExternalContentElementor3, the data key stays the plugin id
     */
    public function getLogName(): string
    {
        return 'elementor4';
    }
}
