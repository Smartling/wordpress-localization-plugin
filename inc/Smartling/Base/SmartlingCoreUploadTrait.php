<?php
namespace Smartling\Base;

use Exception;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\AttachmentHelper;
use Smartling\Helpers\EventParameters\BeforeSerializeContentEventParameters;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SmartlingCoreUploadTrait
 *
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
     * @return bool
     */
    public function sendForTranslationBySubmission(SubmissionEntity $submission)
    {
        $this->getLogger()
             ->debug(vsprintf('Preparing to send submission id = \'%s\'', [$submission->getId()]));
        try {
            $contentEntity = $this->readContentEntity($submission);

            $submission->setSourceContentHash($contentEntity->calculateHash());
            $submission->setSourceTitle($contentEntity->getTitle());

            if (null === $submission->getId()) {
                // generate URI
                $submission->getFileUri();
                $submission = $this->getSubmissionManager()
                                   ->storeEntity($submission);
            }

            $source = [
                'entity' => $contentEntity->toArray(),
                'meta'   => $contentEntity->getMetadata(),
            ];

            $source['meta'] = $source['meta'] ? : [];


            $submission = $this->prepareTargetEntity($submission);

            if (WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT === $submission->getContentType()) {
                $fileData = $this->getAttachmentFileInfoBySubmission($submission);
                $sourceFileFsPath = $fileData['source_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];
                $targetFileFsPath = $fileData['target_path_prefix'] . DIRECTORY_SEPARATOR . $fileData['relative_path'];
                $mediaCloneResult = AttachmentHelper::cloneFile($sourceFileFsPath, $targetFileFsPath, true);
                $result = AttachmentHelper::CODE_SUCCESS === $mediaCloneResult;
                if (AttachmentHelper::CODE_SUCCESS !== $mediaCloneResult) {
                    $message = vsprintf('Error %s happened while working with attachment.', [$mediaCloneResult]);
                    $this->getLogger()
                         ->error($message);
                }
            }

            $this->regenerateTargetThumbnailsBySubmission($submission);

            $params = new BeforeSerializeContentEventParameters($source, $submission, $contentEntity,
                $source['meta']);

            do_action(XmlEncoder::EVENT_SMARTLING_BEFORE_SERIALIZE_CONNENT, $params);

            $this->prepareFieldProcessorValues($submission);

            $xml = XmlEncoder::xmlEncode($source);

            $this->getLogger()
                 ->debug(vsprintf('Serialized fields to XML: %s', [base64_encode($xml),]));

            $this->prepareRelatedSubmissions($submission);

            $result = false;

            if (false === XmlEncoder::hasStringsForTranslation($xml)) {
                $this->getLogger()
                     ->warning(
                         vsprintf(
                             'Prepared XML file for submission = \'%s\' has nothing to translate. Setting status to \'%s\'',
                             [
                                 $submission->getId(),
                                 SubmissionEntity::SUBMISSION_STATUS_FAILED,
                             ]
                         )
                     );
                $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            } else {
                try {
                    $result = self::SEND_MODE === self::SEND_MODE_FILE
                        ? $this->sendFile($submission, $xml)
                        : $this->sendStream($submission, $xml);

                    $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);

                } catch (Exception $e) {
                    $this->getLogger()
                         ->error($e->getMessage());
                    $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
                }
            }

            $submission = $this->getSubmissionManager()
                               ->storeEntity($submission);

            return $result;
        } catch (EntityNotFoundException $e) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);
            $this->getLogger()
                 ->error($e->getMessage());
            $this->getSubmissionManager()
                 ->storeEntity($submission);
        } catch (BlogNotFoundException $e) {
            $this->handleBadBlogId($submission);
        }

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
    public function sendForTranslation($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null)
    {
        $submission = $this->prepareSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog,
            $targetEntity);

        return $this->sendForTranslationBySubmission($submission);
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
    public function createForTranslation(
        $contentType,
        $sourceBlog,
        $sourceEntity,
        $targetBlog,
        $targetEntity = null
    )
    {
        $submission = $this->prepareSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog,
            $targetEntity);

        $contentEntity = $this->readContentEntity($submission);

        if (null === $submission->getId()) {
            $submission->setSourceContentHash($contentEntity->calculateHash());
            $submission->setSourceTitle($contentEntity->getTitle());

            // generate URI
            $submission->getFileUri();
            $submission = $this->getSubmissionManager()
                               ->storeEntity($submission);
        } else {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
        }

        return $this->getSubmissionManager()
                    ->storeEntity($submission);
    }
}