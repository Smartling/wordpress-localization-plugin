<?php

namespace Smartling\ContentTypes\Elementor;

interface ElementFactory
{
    public function fromArray(array $array): Element;
}