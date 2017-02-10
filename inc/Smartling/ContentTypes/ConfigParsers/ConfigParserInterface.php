<?php

namespace Smartling\ContentTypes\ConfigParsers;

/**
 * Interface ConfigParserInterface
 * @package Smartling\ContentTypes\ConfigParsers
 */
interface ConfigParserInterface
{
    /**
     * Parses given config
     * @return void
     */
    public function parse();

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

    /**
     * @return array;
     */
    public function getVisibility();
}