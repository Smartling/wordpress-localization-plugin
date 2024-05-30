<?php

namespace IntegrationTests\tests;

use Smartling\Models\JobInformation;
use Smartling\Models\UserTranslationRequest;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class SubmissionUploadTest extends SmartlingUnitTestCaseAbstract
{
    public function testUploadMultipleTargets()
    {
        $postId = $this->createPost();
        $submissionManager = $this->getSubmissionManager();
        $this->assertEquals(0, $submissionManager->find([SubmissionEntity::FIELD_SOURCE_ID => $postId]), "Expected no submissions to exist for postId=$postId");
        $service = $this->getContainer()->get('service.relations-discovery');
        assert($service instanceof ContentRelationsDiscoveryService);
        $service->createSubmissions(new UserTranslationRequest($postId, 'post', [], [2, 3], new JobInformation(
            'test',
            false,
            'Test multiple targets',
            '',
            '',
            '',
        )));
        $submissions = $submissionManager->find([SubmissionEntity::FIELD_SOURCE_ID => $postId]);
        $this->assertCount(2, $submissions, 'Expected two new submissions to be created');
        $submissionIds = array_reduce($submissions, static function (SubmissionEntity $entity) {
            return $entity->getId();
        });
        $this->addToUploadQueue($submissionIds);
        $this->executeUpload();
        $submissions = $submissionManager->find([SubmissionEntity::FIELD_SOURCE_ID => $postId]);
        foreach ($submissions as $submission) {
            $this->assertNotEquals(0, $submission->getTargetId());
        }
    }
}
