<?php
namespace Smartling\Base;

use Exception;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\EventParameters\BeforeSerializeContentEventParameters;
use Smartling\Helpers\XmlEncoder;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SmartlingCoreUploadTrait
 * @package Smartling\Base
 */
trait SmartlingCoreUploadTrait
{

    /**
     * @param $id
     *
     * @return bool
     */
    public function sendForTranslationBySubmissionId($id)
    {
        return $this->sendForTranslationBySubmission($this->loadSubmissionEntityById($id));
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return SubmissionEntity
     */
    private function renewContentHash(SubmissionEntity $submission)
    {
        $content = $this->getContentHelper()->readSourceContent($submission);
        $newHash = ContentSerializationHelper::calculateHash($this->getContentIoFactory(), $this->getSiteHelper(), $this->getSettingsManager(), $submission);
        $submission->setSourceContentHash($newHash);
        $submission->setOutdated(0);
        $submission->setSourceTitle($content->getTitle());
        $submission = $this->getSubmissionManager()->storeEntity($submission);

        return $submission;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return array
     */
    private function readSourceContentWithMetadataAsArray(SubmissionEntity $submission)
    {
        $source = [
            'entity' =>  $this->getContentHelper()->readSourceContent($submission)->toArray(),
            'meta'   => $this->getContentHelper()->readSourceMetadata($submission),
        ];

        $source['meta'] = $source['meta'] ? : [];

        return $source;
    }

    /**
     * @param SubmissionEntity $submission
     */
    public function cloneWithoutTranslation(SubmissionEntity $submission)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Preparing to clone submission id = \'%s\' (blog = \'%s\', content = \'%s\', type = \'%s\').',
                [
                    $submission->getId(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getContentType(),
                ]
            )
        );

        try {
            if (null === $submission->getId()) {
                $submission->getFileUri();
            }

            $submission = $this->renewContentHash($submission);
            $submission = $this->prepareTargetEntity($submission, true);
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_CLONED);
            $this->getSubmissionManager()->storeEntity($submission);
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent content.');
        } catch (BlogNotFoundException $e) {
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent blog.');
            $this->handleBadBlogId($submission);
        } catch (Exception $e) {
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Error occurred: %s', [$e->getMessage()]));
            $this->getLogger()->error($e->getMessage());
        }
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return bool
     */
    public function sendForTranslationBySubmission(SubmissionEntity $submission)
    {
        $this->getLogger()->debug(
            vsprintf(
                'Preparing to send submission id = \'%s\' (blog = \'%s\', content = \'%s\', type = \'%s\').',
                [
                    $submission->getId(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                    $submission->getContentType(),
                ]
            )
        );

        try {
            $submission = $this->renewContentHash($submission);
            if (null === $submission->getId()) {
                // generate URI
                $submission->getFileUri();
                $submission = $this->getSubmissionManager()->storeEntity($submission);
            }
            $source = $this->readSourceContentWithMetadataAsArray($submission);
            $contentEntity = $this->getContentHelper()->readSourceContent($submission);
            $submission = $this->prepareTargetEntity($submission);

            $params = new BeforeSerializeContentEventParameters($source, $submission, $contentEntity, $source['meta']);
            do_action(ExportedAPI::EVENT_SMARTLING_BEFORE_SERIALIZE_CONTENT, $params);

            $this->prepareFieldProcessorValues($submission);
            $xml = XmlEncoder::xmlEncode($source);

            $this->getLogger()->debug(vsprintf('Serialized fields to XML: %s', [base64_encode($xml),]));
            $this->prepareRelatedSubmissions($submission);
            $result = false;

            if (false === XmlEncoder::hasStringsForTranslation($xml)) {
                $this->getLogger()
                    ->warning(
                        vsprintf(
                            'Prepared XML file for submission = \'%s\' has nothing to translate. Setting status to \'%s\'.',
                            [
                                $submission->getId(),
                                SubmissionEntity::SUBMISSION_STATUS_FAILED,
                            ]
                        )
                    );
                $submission = $this->getSubmissionManager()
                    ->setErrorMessage($submission, 'Nothing is found for translation.');
            } else {
                try {
                    $result = $this->sendFile($submission, $xml);
                    $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                } catch (Exception $e) {
                    $this->getLogger()->error($e->getMessage());
                    $this->getSubmissionManager()
                        ->setErrorMessage($submission, 'Error occurred: %s', [$e->getMessage()]);
                }
            }

            $submission = $this->getSubmissionManager()->storeEntity($submission);

            return $result;
        } catch (EntityNotFoundException $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent content.');
        } catch (BlogNotFoundException $e) {
            $this->getSubmissionManager()->setErrorMessage($submission, 'Submission references non existent blog.');
            $this->handleBadBlogId($submission);
        } catch (Exception $e) {
            $this->getSubmissionManager()
                ->setErrorMessage($submission, vsprintf('Error occurred: %s', [$e->getMessage()]));
            $this->getLogger()->error($e->getMessage());
        }
    }

    public function getOrPrepareSubmission($contentType, $sourceBlogId, $sourceEntityId, $targetBlogId, $status = null)
    {
        $status = $status ? : SubmissionEntity::SUBMISSION_STATUS_NEW;

        /**
         * @var SubmissionEntity $submission
         */
        $submission = $this->getTranslationHelper()->prepareSubmissionEntity($contentType, $sourceBlogId, $sourceEntityId, $targetBlogId);

        $contentEntity = $this->getContentHelper()->readSourceContent($submission);

        if (null === $submission->getId()) {
            $submission->setSourceContentHash('');
            $submission->setSourceTitle($contentEntity->getTitle());

            // generate URI
            $submission->getFileUri();
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        } else {
            $submission->setStatus($status);
            $submission->setLastError('');
        }

        return $this->getSubmissionManager()->storeEntity($submission);
    }

    /**
     * @param string   $contentType
     * @param int      $sourceBlog
     * @param int      $sourceEntity
     * @param int      $targetBlog
     * @param int|null $targetEntity
     *
     * @return bool
     */
    public function createForTranslation($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null)
    {
        /**
         * @var SubmissionEntity $submission
         */
        $submission = $this->getTranslationHelper()->prepareSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity);

        $contentEntity = $this->getContentHelper()->readSourceContent($submission);

        if (null === $submission->getId()) {
            $submission->setSourceContentHash('');
            $submission->setSourceTitle($contentEntity->getTitle());

            // generate URI
            $submission->getFileUri();
            $submission = $this->getSubmissionManager()->storeEntity($submission);
        } else {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
            $submission->setLastError('');
        }

        return $this->getSubmissionManager()->storeEntity($submission);
    }
}