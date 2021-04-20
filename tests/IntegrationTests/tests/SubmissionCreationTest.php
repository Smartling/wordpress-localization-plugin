<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Jobs\JobInformationEntity;
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
        $submission->setBatchUid($batchUid);
        $submission->setJobInfo(new JobInformationEntity('jobName', 'jobUid', 'projectUid'));
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        self::assertEquals($batchUid, $submission->getBatchUid());
        $jobInfo = $submission->getJobInfoWithBatchUid();
        $this->assertEquals('jobName', $jobInfo->getJobName());
        $this->assertEquals('jobUid', $jobInfo->getJobUid());
        $this->assertEquals('projectUid', $jobInfo->getProjectUid());
        $this->assertEquals($batchUid, $jobInfo->getBatchUid());
    }
}
