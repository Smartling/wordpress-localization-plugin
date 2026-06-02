<?php

namespace Smartling\Tests\Smartling\Replacers;

use PHPUnit\Framework\TestCase;
use Smartling\Replacers\TranslateReplacer;

class TranslateReplacerTest extends TestCase
{
    public function testGetLabel(): void
    {
        $this->assertSame('Translate', (new TranslateReplacer())->getLabel());
    }

    public function testUploadPassesValueThrough(): void
    {
        $this->assertSame('hello', (new TranslateReplacer())->processAttributeOnUpload('hello'));
    }

    public function testDownloadUsesTranslatedValue(): void
    {
        $this->assertSame('hola', (new TranslateReplacer())->processAttributeOnDownload('hello', 'hola', null));
    }
}
