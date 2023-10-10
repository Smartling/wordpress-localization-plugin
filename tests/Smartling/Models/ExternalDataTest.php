<?php

namespace Smartling\Models;

use PHPUnit\Framework\TestCase;

class ExternalDataTest extends TestCase {

    public function testAddRelated()
    {
        $x = (new ExternalData())
            ->addRelated(['attachment' => [2]])
            ->addRelated(['attachment' => [1]])
            ->addRelated(['post' => [1]]);
        $this->assertEquals(['attachment' => [2, 1], 'post' => [1]], $x->getRelated());
    }
}
