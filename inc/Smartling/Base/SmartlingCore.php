<?php

namespace Smartling\Base;

use Exception;
use Smartling\ContentTypes\ExternalContentManager;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\GutenbergBlockHelper;
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

    public function __construct(
        private ExternalContentManager $externalContentManager,
        private FileUriHelper $fileUriHelper,
        private GutenbergBlockHelper $gutenbergBlockHelper,
        private PostContentHelper $postContentHelper,
        private UploadQueueManager $uploadQueueManager,
        private XmlHelper $xmlHelper,
        private TestRunHelper $testRunHelper,
        private WordpressFunctionProxyHelper $wpProxy,
    ) {
        parent::__construct();

        $this->wpProxy->add_action(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, [$this, 'cloneContent']);
        $this->wpProxy->add_action(ExportedAPI::ACTION_SMARTLING_PREPARE_SUBMISSION_UPLOAD, [$this, 'prepareUpload']);
        $this->wpProxy->add_action(ExportedAPI::ACTION_SMARTLING_SEND_FOR_TRANSLATION, [$this, 'sendForTranslation']);
        $this->wpProxy->add_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, [$this, 'downloadTranslationBySubmission',]);
        $this->wpProxy->add_action(ExportedAPI::ACTION_SMARTLING_REGENERATE_THUMBNAILS, [$this, 'regenerateTargetThumbnailsBySubmission']);
        $this->wpProxy->add_action(ExportedAPI::FILTER_SMARTLING_PREPARE_TARGET_CONTENT, [$this, 'prepareTargetContent']);
        $this->wpProxy->add_action(ExportedAPI::ACTION_SMARTLING_SYNC_MEDIA_ATTACHMENT, [$this, 'syncAttachment']);
    }

    public function cloneContent(SubmissionEntity $submission): void
    {
        $this->getLogger()->withStringContext([
            'sourceBlogId' => $submission->getSourceBlogId(),
            'sourceId' => $submission->getSourceId(),
            'submissionId' => $submission->getId(),
            'targetBlogId' => $submission->getTargetBlogId(),
            'targetId' => $submission->getTargetId(),
        ], function () use ($submission) {
            $this->getLogger()->info('Start cloning submission');
            if ($submission->isLocked()) {
                $this->getLogger()->notice('Skip cloning submission, is locked');
            }
            $lockedFields = $this->readLockedTranslationFieldsBySubmission($submission);
            $target = ['entity' => [], 'metadata' => []];
            if ($submission->getTargetId() !== 0) {
                $target = $this->getSiteHelper()->withBlog($submission->getTargetBlogId(), function () use ($submission) {
                    $entity = [];
                    $metadata = [];
                    try {
                        $entity = $this->getContentHelper()->readTargetContent($submission)->toArray();
                        $metadata = $this->getContentHelper()->readTargetMetadata($submission);
                    } catch (EntityNotFoundException) {
                        // No target entity exists, that's ok, will create one later
                    }
                    return ['entity' => $entity, 'metadata' => $metadata];
                });
            }
            $submission = $this->prepareTargetContent($submission);
            $entity = $this->getContentHelper()->readTargetContent($submission);
            $content = $entity->toArray();
            foreach ($content as $key => $value) {
                if (array_key_exists($key, $lockedFields['entity'])) {
                    $content[$key] = $lockedFields['entity'][$key];
                    continue;
                }
                if (is_string($value)) {
                    if ($key === 'post_content' && array_key_exists('post_content', $target['entity'])) {
                        $value = $this->postContentHelper->applyContentWithBlockLocks($target['entity']['post_content'], $value);
                    }
                    $content[$key] = $this->gutenbergBlockHelper->replacePostTranslateBlockContent($value, $value, $submission);
                }
            }
            $content = apply_filters(ExportedAPI::FILTER_BEFORE_CLONE_CONTENT_WRITTEN, $content, $submission);
            $this->getContentHelper()->writeTargetContent($submission, $entity->fromArray($content));

            $metadata = [];
            foreach ($lockedFields['meta'] as $key => $value) {
                $metadata[$key] = $value;
            }
            $this->getContentHelper()->writeTargetMetadata($submission, $metadata);

            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
            $submission->setAppliedDate(DateTimeHelper::nowAsString());
            $this->getSubmissionManager()->storeEntity($submission);
            $this->getLogger()->info('Cloned submission');
        });
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
