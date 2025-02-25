<?php

namespace Smartling\Tests\Models;

use PHPUnit\Framework\TestCase;
use Smartling\Models\JobInformation;
use Smartling\Models\UserTranslationRequest;
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
        $x = UserTranslationRequest::fromArray([
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
        $jobInformation1 = $x->getJobInformation();
        $this->assertFalse($jobInformation1->authorize);
        $jobInformation3 = $x->getJobInformation();
        $this->assertEquals($jobDescription, $jobInformation3->description);
        $jobInformation4 = $x->getJobInformation();
        $this->assertEquals($jobDueDate, $jobInformation4->dueDate);
        $jobInformation2 = $x->getJobInformation();
        $this->assertEquals($jobName, $jobInformation2->name);
        $jobInformation5 = $x->getJobInformation();
        $this->assertEquals($jobTimeZone, $jobInformation5->timeZone);
        $jobInformation = $x->getJobInformation();
        $this->assertEquals($jobUid, $jobInformation->id);
    }
}
