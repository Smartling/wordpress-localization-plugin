<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class DetectChangesHelper
 * @package Smartling\Helpers
 */
class DetectChangesHelper
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ContentSerializationHelper
     */
    private $contentSerializationHelper;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return ContentSerializationHelper
     */
    public function getContentSerializationHelper()
    {
        return $this->contentSerializationHelper;
    }

    /**
     * @param ContentSerializationHelper $contentSerializationHelper
     */
    public function setContentSerializationHelper($contentSerializationHelper)
    {
        $this->contentSerializationHelper = $contentSerializationHelper;
    }

    /**
     * @param $blogId
     * @param $contentId
     * @param $contentType
     *
     * @return \Smartling\Submissions\SubmissionEntity[]
     */
    private function getSubmissions($blogId, $contentId, $contentType)
    {
        $params = [
            'source_id'      => $contentId,
            'source_blog_id' => $blogId,
            'content_type'   => $contentType,
        ];

        return $this->getSubmissionManager()->find($params);
    }

    /**
     * @param $blogId
     *
     * @return \Smartling\Settings\ConfigurationProfileEntity[]
     */
    private function getProfiles($blogId)
    {
        return $this->getSettingsManager()->findEntityByMainLocale($blogId);
    }

    /**
     * @param SubmissionEntity $submission
     * @param bool             $needUpdateStatus
     * @param string           $currentHash
     *
     * @return SubmissionEntity
     */
    private function checkSubmissionHash(SubmissionEntity $submission, $needUpdateStatus, $currentHash)
    {
        $this->getLogger()->debug(
            vsprintf('Checking submission id=%s.', [$submission->getId()])
        );
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
                $submission->setStatus($newStatus);
            }
        } else {
            $this->getLogger()->debug(
                vsprintf('Submission id=%s up to date hash. Dropping the Outdated flag.', [$submission->getId()])
            );
            $submission->setOutdated(SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE);
        }

        return $submission;
    }

    /**
     * @param int    $blogId
     * @param int    $contentId
     * @param string $contentType
     */
    public function detectChanges($blogId, $contentId, $contentType)
    {
        $submissions = $this->getSubmissions($blogId, $contentId, $contentType);

        if (0 === count($submissions)) {
            $this->getLogger()->debug(
                vsprintf('No submissions found for %s blog=%s, id=%s', [$contentType, $blogId, $contentId])
            );

            return;
        } else {
            $this->getLogger()->debug(vsprintf('Found %s submissions to check.', [count($submissions)]));
        }

        try {
            $profiles = $this->getProfiles($blogId);

            if (0 < count($profiles)) {
                $profile = $profiles[0];

                $currentHash = $this->getContentSerializationHelper()->calculateHash($submissions[0]);

                /**
                 * @var ConfigurationProfileEntity $profile
                 */
                $needUpdateStatus = $profile->getUploadOnUpdate() === ConfigurationProfileEntity::UPLOAD_ON_CHANGE_AUTO;

                foreach ($submissions as $submission) {
                    $this->checkSubmissionHash($submission, $needUpdateStatus, $currentHash);
                }

                $this->getSubmissionManager()->storeSubmissions($submissions);
            }
        } catch (SmartlingExceptionAbstract $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            return;
        }
    }
}