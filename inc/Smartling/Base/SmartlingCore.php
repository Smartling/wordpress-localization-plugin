<?php

namespace Smartling\Base;

use Exception;
use Smartling\ContentTypes\ExternalContentManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Queue\Queue;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

class SmartlingCore extends SmartlingCoreAbstract
{
    use SmartlingCoreTrait;
    use SmartlingCoreExportApi;
    use CommonLogMessagesTrait;

    private ExternalContentManager $externalContentManager;
    private PostContentHelper $postContentHelper;
    private TestRunHelper $testRunHelper;
    private XmlHelper $xmlHelper;
    private WordpressFunctionProxyHelper $wpProxy;

    public function __construct(ExternalContentManager $externalContentManager, PostContentHelper $postContentHelper, XmlHelper $xmlHelper, TestRunHelper $testRunHelper, WordpressFunctionProxyHelper $wpProxy)
    {
        parent::__construct();

        add_action(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, [$this, 'cloneContent']);
        add_action(ExportedAPI::ACTION_SMARTLING_PREPARE_SUBMISSION_UPLOAD, [$this, 'prepareUpload']);
        add_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, [$this, 'sendForTranslationBySubmission']);
        add_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, [$this, 'downloadTranslationBySubmission',]);
        add_action(ExportedAPI::ACTION_SMARTLING_REGENERATE_THUMBNAILS, [$this, 'regenerateTargetThumbnailsBySubmission']);
        add_filter(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, [$this, 'prepareTargetContent']);
        add_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, [$this, 'syncAttachment']);
        $this->externalContentManager = $externalContentManager;
        $this->postContentHelper = $postContentHelper;
        /** @noinspection UnusedConstructorDependenciesInspection */
        /** @see SmartlingCoreUploadTrait::applyXML() */
        $this->testRunHelper = $testRunHelper;
        /** @noinspection UnusedConstructorDependenciesInspection */
        /** @see SmartlingCoreUploadTrait::readLockedTranslationFieldsBySubmission */
        $this->wpProxy = $wpProxy;
        $this->xmlHelper = $xmlHelper;
    }

    public function cloneContent(SubmissionEntity $submission): void
    {
        $submission = $this->prepareTargetContent($submission);
        if ($submission->getStatus() === SubmissionEntity::SUBMISSION_STATUS_NEW) {
            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
            $submission->setAppliedDate(DateTimeHelper::nowAsString());
            $this->getSubmissionManager()->storeEntity($submission);
            $this->getLogger()->info("Cloned submissionId={$submission->getId()}, sourceBlogId={$submission->getSourceBlogId()}, sourceId={$submission->getSourceId()}, targetBlogId={$submission->getTargetBlogId()}, targetId={$submission->getTargetId()}");
        }
    }

    /**
     * Sends data to smartling directly
     *
     * @param SubmissionEntity $submission
     * @param string           $xmlFileContent
     *
     * @return bool
     */
    protected function sendStream(SubmissionEntity $submission, $xmlFileContent)
    {
        return $this->getApiWrapper()->uploadContent($submission, $xmlFileContent);
    }

    /**
     * Sends data to smartling via temporary file
     *
     * @param SubmissionEntity $submission
     * @param string           $xmlFileContent
     * @param array            $smartlingLocaleList
     *
     * @return bool
     */
    protected function sendFile(SubmissionEntity $submission, $xmlFileContent, array $smartlingLocaleList = [])
    {
        $workDir = sys_get_temp_dir();

        if (is_writable($workDir)) {
            // File extension is needed for Guzzle. Library sets content type
            // depending on file extension (application/xml).
            $tmp_file = tempnam($workDir, '_smartling_temp_') . '.xml';
            $bytesWritten = file_put_contents($tmp_file, $xmlFileContent);

            if (0 === $bytesWritten) {
                $this->getLogger()->warning('Nothing was written to temporary file.');
                return false;
            }

            $tmpFileSize = filesize($tmp_file);
            if ($tmpFileSize !== $bytesWritten || $tmpFileSize !== strlen($xmlFileContent)) {
                $this->getLogger()->warning('Expected size of temporary file doesn\'t equals to real.');
                return false;
            }

            $result = $this->getApiWrapper()->uploadContent($submission, '', $tmp_file, $smartlingLocaleList);
            unlink($tmp_file);
            return $result;
        }

        $this->getLogger()->warning(vsprintf('Working directory : \'%s\' doesn\'t seen to be writable',[$workDir]));
        return false;
    }

    /**
     * Checks and updates submission with given ID
     *
     * @param $id
     *
     * @return array of error messages
     */
    public function checkSubmissionById($id)
    {
        $messages = [];

        try {
            $submission = $this->loadSubmissionEntityById($id);

            $this->checkSubmissionByEntity($submission);
        } catch (SmartlingExceptionAbstract $e) {
            $messages[] = $e->getMessage();
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
        }

        return $messages;
    }

    /**
     * Checks and updates given submission entity
     *
     * @param SubmissionEntity $submission
     *
     * @return array of error messages
     */
    public function checkSubmissionByEntity(SubmissionEntity $submission)
    {
        $messages = [];

        try {
            $this->getLogger()->info(vsprintf(static::$MSG_CRON_CHECK, [
                $submission->getId(),
                $submission->getStatus(),
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
                $submission->getTargetBlogId(),
                $submission->getTargetLocale(),
            ]));

            $submission = $this->getApiWrapper()->getStatus($submission);

            $this->getLogger()->info(vsprintf(static::$MSG_CRON_CHECK_RESULT, [
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
                $submission->getTargetLocale(),
                $submission->getApprovedStringCount(),
                $submission->getCompletedStringCount(),
            ]));


            $this->getSubmissionManager()->storeEntity($submission);
        } catch (SmartlingExceptionAbstract $e) {
            $messages[] = $e->getMessage();
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
        }

        return $messages;
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws SmartlingDbException
     */
    private function loadSubmissionEntityById($id)
    {
        $params = [
            'id' => $id,
        ];

        $entities = $this->getSubmissionManager()->find($params);

        if (count($entities) > 0) {
            return reset($entities);
        }

        $message = vsprintf('Requested SubmissionEntity with id=%s does not exist.', [$id]);

        $this->getLogger()->error($message);
        throw new SmartlingDbException($message);
    }

    /**
     * @param SubmissionEntity[] $items
     * @return array
     */
    public function bulkCheckByIds(array $items)
    {
        $results = [];
        foreach ($items as $item) {
            try {
                $entity = $this->loadSubmissionEntityById($item);
            } catch (SmartlingDbException $e) {
                $this->getLogger()->error('Requested submission that does not exist: ' . (int)$item);
                continue;
            }
            if ($entity->getStatus() === SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS) {
                $this->checkSubmissionByEntity($entity);
                $this->checkEntityForDownload($entity);
                $results[] = $entity;
            }
        }

        return $results;
    }

    /**
     * @param SubmissionEntity $entity
     */
    public function checkEntityForDownload(SubmissionEntity $entity)
    {
        if (100 === $entity->getCompletionPercentage()) {

            $template = 'Cron Job enqueues content to download queue for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'.';

            $message = vsprintf($template, [
                $entity->getId(),
                $entity->getStatus(),
                $entity->getContentType(),
                $entity->getSourceBlogId(),
                $entity->getSourceId(),
                $entity->getTargetBlogId(),
                $entity->getTargetLocale(),
            ]);

            $this->getLogger()->info($message);

            $this->getQueue()->enqueue([$entity->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
        }
    }

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return array
     */
    public function getProjectLocales(ConfigurationProfileEntity $profile)
    {
        $cacheKey = 'profile.locales.' . $profile->getId();
        $cached = $this->getCache()->get($cacheKey);

        if (false === $cached) {
            $cached = $this->getApiWrapper()->getSupportedLocales($profile);
            $this->getCache()->set($cacheKey, $cached);
        }

        return $cached;
    }

    public function handleBadBlogId(SubmissionEntity $submission)
    {
        $profileMainId = $submission->getSourceBlogId();

        $profiles = $this->getSettingsManager()->findEntityByMainLocale($profileMainId);
        if (0 < count($profiles)) {

            $this->getLogger()->warning(vsprintf('Found broken profile. Id:%s. Deactivating.', [
                $profileMainId,
            ]));

            /**
             * @var ConfigurationProfileEntity $profile
             */
            $profile = reset($profiles);
            $profile->setIsActive(0);
            $this->getSettingsManager()->storeEntity($profile);
        }
    }
}
