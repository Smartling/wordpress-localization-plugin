<?php

namespace Smartling\Tests\Smartling\DbAl\WordpressContentEntities;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Helpers\WordpressFunctionProxyHelper;

class TaxonomyEntityStdTest extends TestCase {
    /**
     * @see https://bt.smartling.net/browse/WP-704
     */
    public function testTermMetadataStoredAsScalars()
    {
        $logoField = 'field_609bacf18fec4';
        $logoId = 33404;
        $websiteField = 'field_611e6178a7cee';
        $websiteLink = '';

        $wp = $this->createMock(WordpressFunctionProxyHelper::class);
        $wp->method('get_term_meta')->willReturn([
            'logo' => [$logoId],
            '_logo' => [$logoField],
            'website_link' => [$websiteLink],
            '_website_link' => [$websiteField]
        ]);

        $this->assertEquals([
            'logo' => $logoId,
            '_logo' => $logoField,
            'website_link' => $websiteLink,
            '_website_link' => $websiteField
        ], (new TaxonomyEntityStd('category', [], $wp))->getMetadata());
    }
}
