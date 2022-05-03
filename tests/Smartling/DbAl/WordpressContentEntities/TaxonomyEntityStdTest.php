<?php

namespace Smartling\Tests\Smartling\DbAl\WordpressContentEntities;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Helpers\WordpressFunctionProxyHelper;

class TaxonomyEntityStdTest extends TestCase {
    /**
     * Metadata values for taxonomies should not be stored as array for scalar values
     */
    public function testWP704()
    {
        $logoField = 'field_609bacf18fec4';
        $logoId = 33404;
        $websiteField = 'field_611e6178a7cee';
        $websiteLink = '';

        $wp = $this->createMock(WordpressFunctionProxyHelper::class);
        $wp->method('get_term_meta')->willReturn(['logo' => [$logoId], '_logo' => [$logoField], 'website_link' => [$websiteLink], '_website_link' => [$websiteField]]);
        $x = new TaxonomyEntityStd('category', [], $wp);
        $this->assertEquals(['logo' => $logoId, '_logo' => $logoField, 'website_link' => $websiteLink, '_website_link' => $websiteField], $x->getMetadata());
    }
}
