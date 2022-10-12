<?php

namespace Smartling\Tests\Smartling\Helpers;

use Smartling\Helpers\PluginHelper;
use PHPUnit\Framework\TestCase;
use Smartling\Helpers\WordpressFunctionProxyHelper;

class PluginHelperTest extends TestCase {
    public function testVersionInRange()
    {
        $x = new PluginHelper($this->createMock(WordpressFunctionProxyHelper::class));
        $this->assertTrue($x->versionInRange('1.0.0', '1', '1'));
        $this->assertTrue($x->versionInRange('1.9.9', '1', '1'));
        $this->assertFalse($x->versionInRange('2.0.0', '1', '1'));
        $this->assertTrue($x->versionInRange('1.1.0', '1.1', '1.3'));
        $this->assertTrue($x->versionInRange('1.3.9', '1.1', '1.3'));
        $this->assertFalse($x->versionInRange('1.4.0', '1.1', '1.3'));
        $this->assertFalse($x->versionInRange('2.3.0', '1.1', '1.3'));
        $this->assertTrue($x->versionInRange('2.3.1', '2.3.1', '2.3'));
        $this->assertFalse($x->versionInRange('2.3.0', '2.3.1', '2.3'));
        $this->assertTrue($x->versionInRange('2.3.1', '2.3', '2.3.1'));
        $this->assertFalse($x->versionInRange('2.3.2', '2.3', '2.3.1'));
        $this->assertTrue($x->versionInRange('2.5.2.1', '2.4', '2.5'));
    }
}
