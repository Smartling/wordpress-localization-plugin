<?php

namespace Smartling\Tests\Smartling\Helpers;

use Smartling\Helpers\GutenbergReplacementRule;
use PHPUnit\Framework\TestCase;

class GutenbergReplacementRuleTest extends TestCase {
    public function testSerialization()
    {
        $x = new GutenbergReplacementRule('test"', 'test\'/"', 'some~Id');
        $y = GutenbergReplacementRule::fromString((string)$x);
        $this->assertEquals((string)$x, (string)$y);
    }
}
