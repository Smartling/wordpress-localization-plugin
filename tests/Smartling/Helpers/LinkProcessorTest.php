<?php

namespace Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Extensions\StringHandler;
use Smartling\Submissions\SubmissionEntity;

class LinkProcessorTest extends TestCase {
    public function testGetHandlerListPriority(): void
    {
        $x = new LinkProcessor($this->createMock(SiteHelper::class));
        $mockPriority10 = $this->createMock(StringHandler::class);
        $mockPriority10->method('handle')->willReturn('10');
        $x->addHandler($mockPriority10);
        $mockPriority3 = $this->createMock(StringHandler::class);
        $mockPriority3->method('handle')->willReturn('3');
        $x->addHandler($mockPriority3, 3);
        $mockPriority1 = $this->createMock(StringHandler::class);
        $mockPriority1->method('handle')->willReturn('1');
        $x->addHandler($mockPriority1, 1);
        $mockPriority5 = $this->createMock(StringHandler::class);
        $mockPriority5->method('handle')->willReturn('5');
        $x->addHandler($mockPriority5, 5);
        $x->addHandler($mockPriority3, 3);
        $result = $x->getHandlerList();
        $this->assertCount(5, $result);
        $this->assertEquals('1', $result[0]->handle('', $x, null));
        $this->assertEquals('3', $result[1]->handle('', $x, null));
        $this->assertEquals('3', $result[2]->handle('', $x, null));
        $this->assertEquals('5', $result[3]->handle('', $x, null));
        $this->assertEquals('10', $result[4]->handle('', $x, null));
    }

    public function testProcessUrl(): void
    {
        $siteHelper = $this->createPartialMock(SiteHelper::class, ['restoreBlogId', 'switchBlogId']);
        $submission = $this->createMock(SubmissionEntity::class);
        $noopHandler = $this->createMock(StringHandler::class);
        $noopHandler->method('handle')->willReturnArgument(0);
        $handler1 = $this->createMock(StringHandler::class);
        $handler1Return = '1';
        $handler1->method('handle')->willReturn($handler1Return);
        $handler2 = $this->createMock(StringHandler::class);
        $handler2Return = '2';
        $handler2->method('handle')->willReturn($handler2Return);
        $faultyHandler = $this->createMock(StringHandler::class);
        $faultyHandler->method('handle')->willThrowException(new \TypeError(':('));

        $sourceUrl = '0';

        $x = new LinkProcessor($siteHelper);
        $x->addHandler($noopHandler, 1);

        $this->assertEquals($sourceUrl, $x->processUrl($sourceUrl, $submission));

        $x->addHandler($handler1, 2);
        $this->assertEquals($handler1Return, $x->processUrl($sourceUrl, $submission));

        $x->addHandler($noopHandler, 3);
        $this->assertEquals($handler1Return, $x->processUrl($sourceUrl, $submission));

        $x->addHandler($handler2, 4);
        $this->assertEquals($handler2Return, $x->processUrl($sourceUrl, $submission));

        $x->addHandler($faultyHandler);
        $this->assertEquals($handler2Return, $x->processUrl($sourceUrl, $submission));
    }
}
