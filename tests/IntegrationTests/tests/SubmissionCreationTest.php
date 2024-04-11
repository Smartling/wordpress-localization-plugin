<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Jobs\JobEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class SubmissionCreationTest extends SmartlingUnitTestCaseAbstract
{
    public function testCreateSubmission()
    {
        $postId = $this->createPost();
        $submission = $this->createSubmission('post', $postId);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        self::assertEquals(1, $submission->getId());
    }

    public function testUpdateSubmission()
    {
        $batchUid = '12345';
        $postId = $this->createPost();
        $submission = $this->createSubmission('post', $postId);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission->setJobInfo(new JobEntity('jobName', 'jobUid', 'projectUid'));
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $jobInfo = $submission->getJobInfo();
        $this->assertEquals('jobName', $jobInfo->getJobName());
        $this->assertEquals('jobUid', $jobInfo->getJobUid());
        $this->assertEquals('projectUid', $jobInfo->getProjectUid());
    }
}
