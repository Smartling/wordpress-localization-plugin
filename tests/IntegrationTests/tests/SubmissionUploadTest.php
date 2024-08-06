<?php

namespace IntegrationTests\tests;

use Smartling\ApiWrapperInterface;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Models\JobInformation;
use Smartling\Models\UserTranslationRequest;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class SubmissionUploadTest extends SmartlingUnitTestCaseAbstract
{
    public function testUploadMultipleTargets()
    {
        $api = $this->getContainer()->get('api.wrapper.with.retries');
        assert($api instanceof ApiWrapperInterface);
        $jobName = uniqid('test multiple uploads job ', true);
        $locales = [];
        $postId = $this->createPost();
        $profile = $this->getSettingsManager()->getSingleSettingsProfile(1);
        $submissionManager = $this->getSubmissionManager();
        $targetBlogs = [2, 3];
        foreach ($profile->getTargetLocales() as $locale) {
            if (in_array($locale->getBlogId(), $targetBlogs)) {
                $locales[] = $locale->getSmartlingLocale();
            }
        }
        $job = $api->createJob($profile, ['name' => $jobName, 'locales' => $locales]);
        $this->assertCount(0, $submissionManager->find([SubmissionEntity::FIELD_SOURCE_ID => $postId]), "Expected no submissions to exist for postId=$postId");
        $service = $this->getContainer()->get('service.relations-discovery');
        assert($service instanceof ContentRelationsDiscoveryService);
        $service->createSubmissions(new UserTranslationRequest(
            $postId,
            ContentTypeHelper::CONTENT_TYPE_POST,
            [],
            $targetBlogs,
            new JobInformation($job['translationJobUid'], false, $jobName, '', '', ''),
        ));
        $submissions = $submissionManager->find([SubmissionEntity::FIELD_SOURCE_ID => $postId]);
        $this->assertCount(2, $submissions, 'Expected two new submissions to be created');
        $submissionIds = array_reduce($submissions, static function (array $carry, SubmissionEntity $entity) {
            $carry[] = $entity->getId();
            return $carry;
        }, []);
        $this->addToUploadQueue($submissionIds);
        $this->executeUpload();
        $submissions = $submissionManager->find([SubmissionEntity::FIELD_SOURCE_ID => $postId]);
        foreach ($submissions as $submission) {
            $this->assertEquals(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS, $submission->getStatus());
            $this->assertNotEquals(0, $submission->getTargetId());
            $this->forceSubmissionDownload($submission);
            $submission = $submissionManager->getEntityById($submission->getId());
            $this->assertNotEquals(SubmissionEntity::SUBMISSION_STATUS_FAILED, $submission->getStatus());
        }
    }
}
