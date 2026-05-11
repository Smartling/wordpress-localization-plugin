<?php

namespace Smartling\ContentTypes\Elementor;

class ElementFactory4 extends ElementFactory3 {
    private const ELEMENTS4 = 'Elements4';

    public function __construct()
    {
        parent::__construct();
        $this->loadElements(__DIR__ . DIRECTORY_SEPARATOR . self::ELEMENTS4);
    }
}
