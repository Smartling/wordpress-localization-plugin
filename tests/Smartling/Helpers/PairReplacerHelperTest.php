<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\PairReplacerHelper;

class PairReplacerHelperTest extends TestCase
{
    /**
     * @param string $string
     * @param string $expected
     * @param string $search
     * @param string $replace
     * @dataProvider processStringProvider
     */
    public function testProcessString($string, $expected, $search, $replace)
    {
        $x = new PairReplacerHelper();
        $x->addReplacementPair($search, $replace);
        self::assertEquals($expected, $x->processString($string));
    }

    /**
     * @return array
     */
    public function processStringProvider()
    {
        return [
            [
                '<!-- wp:acf/testimonial {\"id\":\"block_5f1eb3f391cda\",\"name\":\"acf/testimonial\",\"data\":' .
                '{\"media\":\"297\",\"_media\":\"field_5eb1344b55a84\",\"description\":\"text\",\"_description\":' .
                '\"field_5ef64590591dc\"},\"align\":\"\",\"mode\":\"edit\"} /-->',
                '<!-- wp:acf/testimonial {\"id\":\"block_5f1eb3f391cda\",\"name\":\"acf/testimonial\",\"data\":' .
                '{\"media\":\"262\",\"_media\":\"field_5eb1344b55a84\",\"description\":\"text\",\"_description\":' .
                '\"field_5ef64590591dc\"},\"align\":\"\",\"mode\":\"edit\"} /-->',
                "297",
                "262",
            ],
            [
                '<!-- wp:core/image {"id":"297", notId:"2971"} /-->',
                '<!-- wp:core/image {"id":"262", notId:"2971"} /-->',
                "297",
                "262",
            ],
            [
                '<!-- wp:core/image {"id":\'297\', notId:"2971"} /-->',
                '<!-- wp:core/image {"id":\'262\', notId:"2971"} /-->',
                "297",
                "262",
            ],
        ];
    }
}
