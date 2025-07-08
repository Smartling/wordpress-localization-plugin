<?php

namespace Smartling\Helpers;

use Smartling\DbAl\UploadQueueManager;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Models\IntegerIterator;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Controller\LiveNotificationController;

class DetectChangesHelper
{
    use LoggerSafeTrait;

    public function __construct(
        private AcfDynamicSupport $acfDynamicSupport,
        private ContentSerializationHelper $contentSerializationHelper,
        private UploadQueueManager $uploadQueueManager,
        private SettingsManager $settingsManager,
        private SubmissionManager $submissionManager,
    ) {
    }

    /**
     * @return SubmissionEntity[]
     */
    private function getSubmissions(int $blogId, int $contentId, string $contentType): array
    {
        try {
            $params = [
                SubmissionEntity::FIELD_SOURCE_ID       => $contentId,
                SubmissionEntity::FIELD_SOURCE_BLOG_ID  => $blogId,
                SubmissionEntity::FIELD_CONTENT_TYPE    => $contentType,
                SubmissionEntity::FIELD_TARGET_BLOG_ID  => $this->settingsManager
                                                                ->getProfileTargetBlogIdsByMainBlogId($blogId),
                SubmissionEntity::FIELD_STATUS => [
                    SubmissionEntity::SUBMISSION_STATUS_NEW,
                    SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                    SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                    SubmissionEntity::SUBMISSION_STATUS_FAILED,
                    ],
            ];

            return $this->submissionManager->find($params);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * @return ConfigurationProfileEntity[]
     */
    private function getProfiles(int $blogId): array
    {
        return $this->settingsManager->findEntityByMainLocale($blogId);
    }

    private function update(SubmissionEntity $submission, bool $needUpdateStatus, string $currentHash): SubmissionEntity
    {
        $this->getLogger()->debug(vsprintf('Checking submission id=%s.', [$submission->getId()]));
        if ($currentHash !== $submission->getSourceContentHash()) {
            $this->getLogger()->debug(
                vsprintf('Submission id=%s has outdated hash. Setting up Outdated flag.', [$submission->getId()])
            );
            $submission->setOutdated(SubmissionEntity::FLAG_CONTENT_IS_OUT_OF_DATE);
            if ($needUpdateStatus) {
                $newStatus = SubmissionEntity::SUBMISSION_STATUS_NEW;

                $this->getLogger()->debug(
                    vsprintf(
                        'Submission id=%s is Outdated and its status is changed to %s',
                        [$submission->getId(), $newStatus]
                    )
                );

                LiveNotificationController::pushNotification(
                    $this->settingsManager
                        ->getSingleSettingsProfile($submission->getSourceBlogId())
                        ->getProjectId(),
                    LiveNotificationController::getContentId($submission),
                    LiveNotificationController::SEVERITY_WARNING,
                    sprintf('<p>Content outdated for %s id %s blog %s.</p>',
                        $submission->getContentType(),
                        $submission->getSourceId(),
                        $submission->getSourceBlogId()
                    ),
                );

                $submission->setStatus($newStatus);
                $this->uploadQueueManager->enqueue(new IntegerIterator([$submission->getId()]), '');
            }
        } else {
            $this->getLogger()->debug(
                vsprintf('Submission id=%s up to date hash. Dropping the Outdated flag.', [$submission->getId()])
            );
            $submission->setOutdated(SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE);
        }

        return $submission;
    }

    public function detectChanges(int $blogId, int $contentId, string $contentType): void
    {
        $submissions = $this->getSubmissions($blogId, $contentId, $contentType);

        if (0 === count($submissions)) {
            $submissions = $this->getSubmissions($blogId, $contentId, AcfDynamicSupport::POST_TYPE_ACF_FIELD_GROUP);
            foreach ($submissions as $submission) {
                $this->getLogger()->debug("Sync field group submissionId={$submission->getId()}");
                $this->acfDynamicSupport->syncFieldGroup($submission);
            }

            $this->getLogger()->debug(
                vsprintf('No submissions found for %s blog=%s, id=%s', [$contentType, $blogId, $contentId])
            );

            return;
        }

        $this->getLogger()->debug(vsprintf('Found %s submissions to check.', [count($submissions)]));

        try {
            $profiles = $this->getProfiles($blogId);

            if (0 < count($profiles)) {
                $profile = $profiles[0];

                $currentHash = $this->contentSerializationHelper->calculateHash($submissions[0]);

                $needUpdateStatus = $profile->getUploadOnUpdate() === ConfigurationProfileEntity::UPLOAD_ON_CHANGE_AUTO;

                foreach ($submissions as $submission) {
                    $this->update($submission, $needUpdateStatus, $currentHash);
                }

                $this->submissionManager->storeSubmissions($submissions);
            }
        } catch (\Exception $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            return;
        }
    }
}
