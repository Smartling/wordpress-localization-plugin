<?php

namespace Smartling\Tests\Models;

use PHPUnit\Framework\TestCase;
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
        $this->assertFalse($x->getJobInformation()->isAuthorize());
        $this->assertEquals($jobDescription, $x->getJobInformation()->getDescription());
        $this->assertEquals($jobDueDate, $x->getJobInformation()->getDueDate());
        $this->assertEquals($jobName, $x->getJobInformation()->getName());
        $this->assertEquals($jobTimeZone, $x->getJobInformation()->getTimeZone());
        $this->assertEquals($jobUid, $x->getJobInformation()->getId());
    }

    public function testFromArrayBulkUploadWithEmptySourceId()
    {
        $targetBlogId = 2;
        $ids = [13, 14, 15];
        $x = UserTranslationRequest::fromArray([
            'job' => [
                'id' => '',
                'name' => '',
                'description' => '',
                'dueDate' => '',
                'timeZone' => 'Europe/Kyiv',
                'authorize' => 'true',
            ],
            'formAction' => ContentRelationsHandler::FORM_ACTION_UPLOAD,
            'source' => ['id' => [], 'contentType' => 'post'],
            'relations' => [],
            'targetBlogIds' => (string)$targetBlogId,
            'ids' => $ids,
        ]);
        $this->assertTrue($x->isBulk());
        $this->assertEquals($ids, $x->getIds());
        $this->assertEquals('post', $x->getContentType());
    }

    public function testFromArrayDefaultsDescriptionToBulkSubmitWhenBulk()
    {
        $x = UserTranslationRequest::fromArray($this->buildArray(['ids' => [13, 14, 15]]));
        $this->assertEquals('From Bulk Submit', $x->getDescription());
    }

    public function testFromArrayDefaultsDescriptionToWidgetWhenNotBulk()
    {
        $x = UserTranslationRequest::fromArray($this->buildArray());
        $this->assertEquals('From Widget', $x->getDescription());
    }

    public function testFromArrayPreservesExplicitTopLevelDescription()
    {
        $x = UserTranslationRequest::fromArray($this->buildArray(['description' => 'My custom description']));
        $this->assertEquals('My custom description', $x->getDescription());
    }

    private function buildArray(array $overrides = []): array
    {
        return array_merge([
            'job' => [
                'id' => '',
                'name' => '',
                'description' => '',
                'dueDate' => '',
                'timeZone' => 'Europe/Kyiv',
                'authorize' => 'true',
            ],
            'formAction' => ContentRelationsHandler::FORM_ACTION_UPLOAD,
            'source' => ['id' => [5], 'contentType' => 'post'],
            'relations' => [],
            'targetBlogIds' => '2',
        ], $overrides);
    }
}
