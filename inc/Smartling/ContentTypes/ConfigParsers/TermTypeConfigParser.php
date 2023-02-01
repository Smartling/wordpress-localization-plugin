<?php

namespace Smartling\ContentTypes\ConfigParsers;

class TermTypeConfigParser extends ConfigParserAbstract
{
    public function parse(): void
    {
        $rawConfig = $this->getRawConfig();
        if (array_key_exists('taxonomy', $rawConfig)) {
            $this->hydrate($rawConfig['taxonomy']);
        }
    }
}
