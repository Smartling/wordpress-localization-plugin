<?php

namespace Smartling\Base;

use Exception;
use Smartling\ContentTypes\ContentTypeNavigationMenu;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\EventParameters\ProcessRelatedContentParams;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Queue\Queue;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SmartlingCore
 * @package Smartling\Base
 */
class SmartlingCore extends SmartlingCoreAbstract
{
    use SmartlingCoreTrait;
    use SmartlingCoreExportApi;
    use CommonLogMessagesTrait;

    private $postContentHelper;
    private TestRunHelper $testRunHelper;
    private $xmlHelper;

    public function __construct(PostContentHelper $postContentHelper, XmlHelper $xmlHelper, TestRunHelper $testRunHelper)
    {
        parent::__construct();

        add_action(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, [$this, 'cloneContent']);
        add_action(ExportedAPI::ACTION_SMARTLING_PREPARE_SUBMISSION_UPLOAD, [$this, 'prepareUpload']);
        add_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, [$this, 'sendForTranslationBySubmission']);
        add_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, [$this, 'downloadTranslationBySubmission',]);
        add_action(ExportedAPI::ACTION_SMARTLING_REGENERATE_THUMBNAILS, [$this, 'regenerateTargetThumbnailsBySubmission']);
        add_filter(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, [$this, 'prepareTargetContent']);
        add_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, [$this, 'syncAttachment']);
        $this->postContentHelper = $postContentHelper;
        /** @noinspection UnusedConstructorDependenciesInspection */
        /** @see SmartlingCoreUploadTrait::applyXML() */
        $this->testRunHelper = $testRunHelper;
        $this->xmlHelper = $xmlHelper;
    }

    public function cloneContent(SubmissionEntity $submission): void
    {
        $this->applyXML($submission, $this->getXMLFiltered($submission), $this->xmlHelper, $this->postContentHelper);
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return void
     *
     * @throws BlogNotFoundException
     */
    public function prepareRelatedSubmissions(SubmissionEntity $submission)
    {
        $this->getLogger()->info(vsprintf('Searching for related content for submission = \'%s\' for translation', [
            $submission->getId(),
        ]));
        $originalEntity = $this->getContentHelper()->readSourceContent($submission);
        $relatedContentTypes = $originalEntity->getRelatedTypes();
        $accumulator = [
            'category' => [],
            'post_tag' => [],
        ];
        try {
            if (!empty($relatedContentTypes)) {
                foreach ($relatedContentTypes as $contentType) {
                    try {
                        $params = new ProcessRelatedContentParams($submission, $contentType, $accumulator);
                        do_action(ExportedAPI::ACTION_SMARTLING_PROCESSOR_RELATED_CONTENT, $params);
                    } catch (\Exception $e) {
                        $this->getLogger()->warning(
                            vsprintf('An unhandled exception occurred while processing related content for submission=%s', [$submission->getId()])
                        );
                    }
                }
            }

            if ($submission->getContentType() !== ContentTypeNavigationMenu::WP_CONTENT_TYPE) {
                $this->getContentHelper()->ensureTargetBlogId($submission);
                $this->getLogger()
                    ->debug(vsprintf('Preparing to assign accumulator: %s', [var_export($accumulator, true)]));
                foreach ($accumulator as $type => $ids) {
                    $this->getLogger()
                        ->debug(vsprintf('Assigning term (type = \'%s\', ids = \'%s\') to content (type = \'%s\', id = \'%s\') on blog= \'%s\'.', [
                            $type,
                            implode(',', $ids),
                            $submission->getContentType(),
                            $submission->getTargetId(),
                            $submission->getTargetBlogId(),
                        ]));

                    wp_set_post_terms($submission->getTargetId(), $ids, $type);
                }
                $this->getContentHelper()->ensureRestoredBlogId();
            } else {
                $this->getCustomMenuHelper()->assignMenuItemsToMenu(
                    (int)$submission->getTargetId(),
                    (int)$submission->getTargetBlogId(),
                    $accumulator[ContentTypeNavigationMenu::WP_CONTENT_TYPE]
                );
            }
        } catch (BlogNotFoundException $e) {
            $message = vsprintf('Inconsistent multisite installation. %s', [$e->getMessage()]);
            $this->getLogger()->emergency($message);

            throw $e;
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
