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

    public function testMergeStrings()
    {
        $x = (new ExternalData(['pathA' => 'stringA']));
        $this->assertEquals(
            ['pathA' => 'stringA', 'pathB' => 'stringB'],
            $x->merge(new ExternalData(['pathB' => 'stringB']))->getStrings(),
        );
        $this->assertEquals(
            ['pathA' => 'stringB'],
            $x->merge(new ExternalData(['pathA' => 'stringB']))->getStrings(),
        );
    }
}
