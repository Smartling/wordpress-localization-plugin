<?php

namespace Smartling\Extensions\Acf;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\SettingsManager;

class AcfDynamicSupportTest extends TestCase {

    public function testGetReplacerIdForField()
    {
        $x = new class(
            new ArrayHelper(),
            $this->createMock(SettingsManager::class),
            $this->createMock(SiteHelper::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        ) extends AcfDynamicSupport {
            public
            function run(): void
            {
            }
        };
        $x->addCopyRules(['field_bbbbbbbbbbbbb']);
        $this->assertEquals(ReplacerFactory::REPLACER_COPY, $x->getReplacerIdForField(
            ['someAttribute' => 'test', '_someAttribute' => 'field_aaaaaaaaaaaaa_field_bbbbbbbbbbbbb'],
            'someAttribute',
        ));
    }
}
