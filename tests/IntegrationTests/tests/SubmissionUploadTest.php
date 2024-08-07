<?php

namespace IntegrationTests\tests;

use Smartling\ApiWrapperInterface;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Models\IntegerIterator;
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

    public function testUploadAttachment()
    {
        $api = $this->getContainer()->get('api.wrapper.with.retries');
        assert($api instanceof ApiWrapperInterface);
        $attachmentId = $this->createAttachment();
        $jobName = uniqid('test upload attachments job ', true);
        $locales = [];
        $postId = $this->createPost(content: <<<HTML
<!-- wp:image {"id":$attachmentId,"sizeSlug":"full","linkDestination":"none","smartlingLockId":"tvgff"} -->
<figure class="wp-block-image size-full"><img src="http://test.com/wp-content/uploads/2024/08/06.jpg" alt="" class="wp-image-$attachmentId"/></figure>
<!-- /wp:image -->
HTML);
        $profile = $this->getSettingsManager()->getSingleSettingsProfile(1);
        $targetBlogs = [2];
        foreach ($profile->getTargetLocales() as $locale) {
            if (in_array($locale->getBlogId(), $targetBlogs)) {
                $locales[] = $locale->getSmartlingLocale();
            }
        }
        $job = $api->createJob($profile, ['name' => $jobName, 'locales' => $locales]);
        $submissionManager = $this->getSubmissionManager();
        $existingSubmissionCount = count($submissionManager->find([1 => 1]));
        $service = $this->getContainer()->get('service.relations-discovery');
        assert($service instanceof ContentRelationsDiscoveryService);
        $service->createSubmissions(new UserTranslationRequest(
            $postId,
            ContentTypeHelper::CONTENT_TYPE_POST,
            [1 => [2 => ['attachment' => [$attachmentId]]]],
            $targetBlogs,
            new JobInformation($job['translationJobUid'], false, $jobName, '', '', ''),
        ));
        $this->assertCount($existingSubmissionCount + 2, $submissionManager->find([1 => 1]));
        // findOne returns null on multiple submissions
        $submissionPost = $submissionManager->findOne([SubmissionEntity::FIELD_SOURCE_ID => $postId]);
        $this->assertNotNull($submissionPost);
        $submissionAttachment = $submissionManager->findOne([SubmissionEntity::FIELD_SOURCE_ID => $attachmentId]);
        $this->assertNotNull($submissionAttachment);
        $uploadQueueManager = $this->getContainer()->get('manager.upload.queue');
        assert($uploadQueueManager instanceof UploadQueueManager);
        $this->assertNotEquals(0, $uploadQueueManager->count());
        $submissionsToUpload = 0;
        do {
            $uploadQueueItem = $uploadQueueManager->dequeue();
            $submissionsToUpload += count($uploadQueueItem->getSubmissions());
            $batchUid = $uploadQueueItem->getBatchUid();
            $this->assertNotEquals('', $batchUid);
        } while ($uploadQueueManager->count() > 0);
        $this->assertEquals(2, $submissionsToUpload);
        $uploadQueueManager->enqueue(
            new IntegerIterator([$submissionPost->getId(), $submissionAttachment->getId()]),
            $batchUid,
        );
        $this->executeUpload();
        $this->assertEquals(
            SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
            $submissionManager->findOne([SubmissionEntity::FIELD_SOURCE_ID => $postId])->getStatus()
        );
        $this->assertEquals(
            SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
            $submissionManager->findOne([SubmissionEntity::FIELD_SOURCE_ID => $attachmentId])->getStatus()
        );
    }
}
