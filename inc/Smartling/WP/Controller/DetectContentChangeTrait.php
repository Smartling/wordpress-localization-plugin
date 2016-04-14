<?php

namespace Smartling\WP\Controller;

use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class DetectContentChangeTrait
 * @package Smartling\WP\Controller
 */
trait DetectContentChangeTrait
{
    /**
     * @param int    $sourceBlogId
     * @param int    $sourceId
     * @param string $contentType
     */
    public function detectChange($sourceBlogId, $sourceId, $contentType)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Checking if content has changed for %s blog=%s, id=%s',
                [
                    $contentType,
                    $sourceBlogId,
                    $sourceId,
                ]
            )
        );

        $params = [
            'source_id'      => $sourceId,
            'source_blog_id' => $sourceBlogId,
            'content_type'   => $contentType,
        ];

        $submissions = $this->getManager()->find($params);

        if (0 === count($submissions)) {
            $this->getLogger()->debug(
                vsprintf(
                    'No Checking if content has changed for %s blog=%s, id=%s',
                    [
                        $contentType,
                        $sourceBlogId,
                        $sourceId,
                    ]
                )
            );

            return;
        }

        $this->getLogger()->debug(
            vsprintf(
                'Found %s submissions to check.',
                [
                    count($submissions),
                ]
            )
        );


        try {
            $profiles = $this->getCore()->getSettingsManager()->findEntityByMainLocale($sourceBlogId);

            if (0 < count($profiles)) {
                $profile = reset($profiles);

                $currentHash = ContentSerializationHelper::calculateHash(
                    $this->getCore()->getContentIoFactory(),
                    $this->getCore()->getSiteHelper(),
                    $this->getCore()->getSettingsManager(),
                    reset($submissions)
                );

                /**
                 * @var ConfigurationProfileEntity $profile
                 */
                $needUpdateStatus = $profile->getUploadOnUpdate() === ConfigurationProfileEntity::UPLOAD_ON_CHANGE_AUTO;

                foreach ($submissions as $submission) {
                    $this->getLogger()->debug(vsprintf('Checking submission id=%s.', [$submission->getId()]));

                    if ($currentHash !== $submission->getSourceContentHash()) {
                        $this->getLogger()->debug(
                            vsprintf(
                                'Submission id=%s has outdated hash. Setting up Outdated flag.',
                                [
                                    $submission->getId(),
                                ]
                            )
                        );
                        $submission->setOutdated(1);

                        if ($needUpdateStatus)
                        {
                            $this->getLogger()->debug(
                                vsprintf(
                                    'Submission id=%s is Outdated and its status is changed to %s',
                                    [
                                        $submission->getId(),
                                        SubmissionEntity::SUBMISSION_STATUS_NEW
                                    ]
                                )
                            );

                            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                        }
                    } else {
                        $this->getLogger()->debug(
                            vsprintf(
                                'Submission id=%s up to date hash. Dropping the Outdated flag.',
                                [
                                    $submission->getId(),
                                ]
                            )
                        );
                        $submission->setOutdated(0);

                    }
                }

                $this->getManager()->storeSubmissions($submissions);

            }
        } catch (SmartlingExceptionAbstract $e) {
            $this->getLogger()->warning($e->getMessage(), ['exception' => $e]);

            return;
        }
    }
}