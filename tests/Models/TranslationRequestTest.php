<?php

namespace Smartling\Tests\Models;

use PHPUnit\Framework\TestCase;
use Smartling\Models\TranslationRequest;
use Smartling\Services\ContentRelationsHandler;

class TranslationRequestTest extends TestCase
{
    public function testFromArray()
    {
        $jobAuthorize = 'false';
        $jobDescription = 'Test Job Description';
        $jobDueDate = '2022-02-20 20:02';
        $jobName = 'Test Job Name';
        $jobTimeZone = 'Europe/Kyiv';
        $jobUid = 'jobUid';
        $sourceContentType = 'post';
        $sourceId = 13;
        $targetBlogId = 2;
        $x = TranslationRequest::fromArray([
            'job' => [
                'id' => $jobUid,
                'name' => $jobName,
                'description' => $jobDescription,
                'dueDate' => $jobDueDate,
                'timeZone' => $jobTimeZone,
                'authorize' => $jobAuthorize,
            ],
            'formAction' => ContentRelationsHandler::FORM_ACTION_UPLOAD,
            'source' => ['id' => [$sourceId], 'contentType' => $sourceContentType],
            'relations' => [
                1 => [$targetBlogId => ['post' => [3]]],
                2 => [$targetBlogId => ['attachment' => [5]]],
            ],
            'targetBlogIds' => (string)$targetBlogId,
        ]);
        $this->assertEquals($sourceId, $x->getContentId());
        $this->assertEquals($sourceContentType, $x->getContentType());
        $this->assertEquals([1 => [$targetBlogId => ['post' => [3]]], 2 => [$targetBlogId => ['attachment' => [5]]]], $x->getRelationsOrdered());
        $this->assertFalse($x->getJobInformation()->isAuthorize());
        $this->assertEquals($jobDescription, $x->getJobInformation()->getDescription());
        $this->assertEquals($jobDueDate, $x->getJobInformation()->getDueDate());
        $this->assertEquals($jobName, $x->getJobInformation()->getName());
        $this->assertEquals($jobTimeZone, $x->getJobInformation()->getTimeZone());
        $this->assertEquals($jobUid, $x->getJobInformation()->getId());
    }
}
