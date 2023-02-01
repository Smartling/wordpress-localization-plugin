<?php

namespace Smartling\ContentTypes\ConfigParsers;

class PostTypeConfigParser extends ConfigParserAbstract
{
    public function parse(): void
    {
        $rawConfig = $this->getRawConfig();
        if (array_key_exists('type', $rawConfig)) {
            $this->hydrate($rawConfig['type']);
        }
    }
}
