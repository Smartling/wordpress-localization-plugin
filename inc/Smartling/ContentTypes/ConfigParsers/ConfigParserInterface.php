<?php

namespace Smartling\ContentTypes\ConfigParsers;

interface ConfigParserInterface
{
    public function parse(): void;

    /**
     * returns true if config is valid
     * @return mixed
     */
    public function isValid();

    /**
     * returns true if widget has to be shown
     * @return mixed
     */
    public function hasWidget();

    public function getVisibility(string $page): bool;
}