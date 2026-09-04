<?php

namespace IntegrationTests\tests;

use Smartling\Helpers\DateTimeHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

class CloneTest extends SmartlingUnitTestCaseAbstract {
    public function testIsClonedClearedOnTranslation(): void
    {
        $currentBlogId = get_current_blog_id();
        $targetBlogId = 2;
        $this->assertNotEquals($currentBlogId, $targetBlogId);
        $postId = $this->createPost(title: 'Clear cloned flag', content: 'Post content');
        $contentType = 'post';
        $submission = $this->createSubmission($contentType, $postId, $currentBlogId, $targetBlogId);
        $submission->setIsCloned(1);
        $submission = $this->getSubmissionManager()->storeEntity($submission);
        $submission = $this->getSubmissionManager()->getEntityById($submission->getId());
        $this->assertTrue($submission->isCloned());
        $apiWrapper = $this->getApiWrapper();
        $jobName = 'testIsClonedClearedOnTranslation';
        $profile = $this->getProfileById(1);
        $response = $apiWrapper->listJobs($profile, $jobName);
        $jobUid = $response['items'][0]['translationJobUid'] ?? null;
        $jobDescription = 'Test job';

        if ($jobUid === null) {
            try {
                $result = $apiWrapper->createJob($profile, [
                    'name' => $jobName,
                    'description' => $jobDescription,
                ]);
            } catch (SmartlingApiException) {
                $jobName = $jobName(' ' . date(DateTimeHelper::getWordpressTimeFormat()));
                $result = $apiWrapper->createJob($profile, [
                    'name' => $jobName,
                    'description' => $jobDescription,
                ]);
            }

            $jobUid = $result['translationJobUid'];
        }

        $this->getContentRelationsDiscoveryService()->bulkUpload(
            false,
            [$postId],
            $contentType,
            $currentBlogId,
            new JobEntity($jobName, $jobUid, $profile->getProjectId()),
            $profile,
            [$targetBlogId],
        );
        $submission = $this->getSubmissionManager()->getEntityById($submission->getId());
        $this->assertFalse($submission->isCloned());
    }
}
