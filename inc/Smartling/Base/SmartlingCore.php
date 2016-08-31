<?php
namespace Smartling\Base;

use Exception;
use Smartling\Queue\Queue;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\MenuItemEntity;
use Smartling\DbAl\WordpressContentEntities\WidgetEntity;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingExceptionAbstract;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Specific\SurveyMonkey\PrepareRelatedSMSpecificTrait;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SmartlingCore
 * @package Smartling\Base
 */
class SmartlingCore extends SmartlingCoreAbstract
{

    use SmartlingCoreTrait;

    use PrepareRelatedSMSpecificTrait;

    use SmartlingCoreExportApi;

    use CommonLogMessagesTrait;

    public function __construct()
    {
        add_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, [$this, 'sendForTranslationBySubmission']);
        add_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, [$this, 'downloadTranslationBySubmission',]);
        add_action(ExportedAPI::ACTION_SMARTLING_CLONE_CONTENT, [$this, 'cloneWithoutTranslation']);
    }

    /**
     * current mode to send data to Smartling
     */
    const SEND_MODE = self::SEND_MODE_FILE;

    /**
     * @param SubmissionEntity $submission
     */
    private function fixCategoryHierarchy(SubmissionEntity $submission)
    {
        if (WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY !== $submission->getContentType()) {
            return;
        }

        $originalEntity = $this->getContentHelper()->readSourceContent($submission);
        $parent = $originalEntity->getParent();

        $newParentId = 0;
        if (0 < (int)$parent) {
            $this->getLogger()->debug(
                vsprintf(
                    'Found parent for %s blog=%s, id=%s, parentId=%s',
                    [
                        $submission->getContentType(),
                        $submission->getSourceBlogId(),
                        $submission->getSourceId(),
                        $parent,
                    ]
                )
            );

            //search for parent submission
            $params = [
                'source_blog_id' => $submission->getSourceBlogId(),
                'source_id'      => (int)$parent,
                'target_blog_id' => $submission->getTargetBlogId(),
                'content_type'   => $submission->getContentType(),
            ];

            $parentSubmissions = $this->getSubmissionManager()->find($params);

            if (0 < count($parentSubmissions)) {
                $parentSubmission = reset($parentSubmissions);
                $newParentId = (int)$parentSubmission->getTargetId();
            } else {
                $newParentId = (int)$this->translateAndGetTargetId(
                    WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY,
                    $submission->getSourceBlogId(),
                    $parent,
                    $submission->getTargetBlogId()
                );
            }
        }
        
        $translation = $this->getContentHelper()->readTargetContent($submission);

        if ((int)$translation->getParent() !== (int)$newParentId) {
            $translation->setParent($newParentId);
            $this->getContentHelper()->writeTargetContent($submission, $translation);
        }

    }

    /**
     * @param SubmissionEntity $submission
     */
    private function fixPageHierarchy(SubmissionEntity $submission)
    {
        if (WordpressContentTypeHelper::CONTENT_TYPE_PAGE !== $submission->getContentType()) {
            return;
        }

        $originalEntity = $this->getContentHelper()->readSourceContent($submission);
        $parent = $originalEntity->getPostParent();

        $newParentId = 0;
        if (0 < (int)$parent) {
            $this->getLogger()->debug(
                vsprintf(
                    'Found parent for %s blog=%s, id=%s, parentId=%s',
                    [
                        $submission->getContentType(),
                        $submission->getSourceBlogId(),
                        $submission->getSourceId(),
                        $parent,
                    ]
                )
            );

            //search for parent submission
            $params = [
                'source_blog_id' => $submission->getSourceBlogId(),
                'source_id'      => (int)$parent,
                'target_blog_id' => $submission->getTargetBlogId(),
                'content_type'   => $submission->getContentType(),
            ];

            $parentSubmissions = $this->getSubmissionManager()->find($params);

            if (0 < count($parentSubmissions)) {
                $parentSubmission = reset($parentSubmissions);
                $newParentId = (int)$parentSubmission->getTargetId();
            } else {
                $newParentId = (int)$this->translateAndGetTargetId(
                    $submission->getContentType(),
                    $submission->getSourceBlogId(),
                    $parent,
                    $submission->getTargetBlogId()
                );
            }
        }

        $translation = $this->getContentHelper()->readTargetContent($submission);

        if ((int)$translation->getPostParent() !== (int)$newParentId) {
            $translation->setPostParent($newParentId);
            $this->getContentHelper()->writeTargetContent($submission, $translation);
        }

    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $contentType
     * @param array            $accumulator
     */
    private function processRelatedTerm(SubmissionEntity $submission, $contentType, & $accumulator)
    {
        $this->getLogger()->debug(vsprintf('Searching for terms (%s) related to submission = \'%s\'.', [
            $contentType,
            $submission->getId(),
        ]));

        if (WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY === $submission->getContentType()) {
            $this->fixCategoryHierarchy($submission);
        }

        if (WordpressContentTypeHelper::CONTENT_TYPE_WIDGET !== $submission->getContentType()
            && in_array($contentType, WordpressContentTypeHelper::getSupportedTaxonomyTypes())
            && WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY !== $submission->getContentType()
        ) {
            $terms = $this->getCustomMenuHelper()->getTerms($submission, $contentType);
            if (0 < count($terms)) {
                foreach ($terms as $element) {
                    $this->getLogger()
                        ->debug(vsprintf('Sending for translation term = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
                            $element->taxonomy,
                            $element->term_id,
                            $submission->getId(),
                        ]));

                    $contentTranslationId = $this->translateAndGetTargetId(
                        $element->taxonomy,
                        $submission->getSourceBlogId(),
                        $element->term_id,
                        $submission->getTargetBlogId()
                    );

                    $accumulator[$contentType][] = $contentTranslationId;

                }
            }
        }
    }

    /**
     * @param SubmissionEntity $submission
     */
    private function processRelatedPage(SubmissionEntity $submission)
    {
        $this->getLogger()->debug(vsprintf('Searching for pages, related to submission = \'%s\'.', [
            $submission->getId(),
        ]));

        if (WordpressContentTypeHelper::CONTENT_TYPE_PAGE === $submission->getContentType()) {
            $this->fixPageHierarchy($submission);
        }
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $contentType
     * @param array            $accumulator
     *
     * @throws BlogNotFoundException
     */
    private function processRelatedMenu(SubmissionEntity $submission, $contentType, &$accumulator)
    {
        if (WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM === $contentType) {
            $this->getLogger()->debug(vsprintf('Searching for menuItems related to submission = \'%s\'.', [
                $submission->getId(),
            ]));

            $ids = $this->getCustomMenuHelper()
                ->getMenuItems($submission->getSourceId(), $submission->getSourceBlogId());

            $menuItemIds = [];

            /** @var MenuItemEntity $menuItem */
            foreach ($ids as $menuItemEntity) {

                $menuItemIds[]=$menuItemEntity->getPK();

                $this->getLogger()
                    ->debug(vsprintf('Sending for translation entity = \'%s\' id = \'%s\' related to submission = \'%s\'.', [
                        WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
                        $menuItemEntity->getPK(),
                        $submission->getId(),
                    ]));

                $menuItemSubmission = $this->fastSendForTranslation(WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM, $submission->getSourceBlogId(), $menuItemEntity->getPK(), $submission->getTargetBlogId());

                $originalMenuItemMeta = $this->getContentHelper()->readSourceMetadata($menuItemSubmission);

                $originalMenuItemMeta = ArrayHelper::simplifyArray($originalMenuItemMeta);

                if (in_array($originalMenuItemMeta['_menu_item_type'], [
                    'taxonomy',
                    'post_type',
                ])) {
                    $this->getLogger()
                        ->debug(vsprintf('Sending for translation object = \'%s\' related to \'%s\' related to submission = \'%s\'.', [
                            $originalMenuItemMeta['_menu_item_object'],
                            WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU_ITEM,
                            $menuItemEntity->getPK(),
                        ]));
                    $relatedObjectId = $this->translateAndGetTargetId($originalMenuItemMeta['_menu_item_object'], $submission->getSourceBlogId(), (int)$originalMenuItemMeta['_menu_item_object_id'], $submission->getTargetBlogId());

                    $originalMenuItemMeta['_menu_item_object_id'] = $relatedObjectId;
                }

                $this->getContentHelper()->writeTargetMetadata($menuItemSubmission, $originalMenuItemMeta);

                $accumulator[WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU][] = $menuItemSubmission->getTargetId();
            }

            $this->getCustomMenuHelper()->rebuildMenuHierarchy(
                $submission->getSourceBlogId(),
                $submission->getTargetBlogId(),
                $menuItemIds
            );
        }
    }

    /**
     * @param SubmissionEntity $submission
     * @param string           $contentType
     */
    private function processMenuRelatedToWidget(SubmissionEntity $submission, $contentType)
    {
        if (WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU === $contentType &&
            WordpressContentTypeHelper::CONTENT_TYPE_WIDGET === $submission->getContentType()
        ) {
            $this->getLogger()->debug(vsprintf('Searching for menu related to widget for submission = \'%s\'.', [
                $submission->getId(),
            ]));
            $originalEntity = $this->getContentHelper()->readSourceContent($submission);

            $_settings = $originalEntity->getSettings();

            if (array_key_exists('nav_menu', $_settings)) {
                $menuId = (int)$_settings['nav_menu'];
            } else {
                $menuId = 0;
            }
            /**
             * @var WidgetEntity $originalEntity
             */


            if (0 !== $menuId) {

                $this->getLogger()
                    ->debug(vsprintf('Sending for translation menu related to widget id = \'%s\' related to submission = \'%s\'.', [
                        $originalEntity->getPK(),
                        $submission->getId(),
                    ]));

                $newMenuId = $this->translateAndGetTargetId(WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU, $submission->getSourceBlogId(), $menuId, $submission->getTargetBlogId());

                /**
                 * @var WidgetEntity $targetContent
                 */
                $targetContent = $this->getContentHelper()->readTargetContent($submission);

                $settings = $targetContent->getSettings();
                $settings['nav_menu'] = $newMenuId;
                $targetContent->setSettings($settings);

                $this->getContentHelper()->writeTargetContent($submission, $targetContent);
            }

        }
    }

    /**
     * @param SubmissionEntity $submission
     */
    private function processFeaturedImage(SubmissionEntity $submission)
    {
        $originalMetadata = $this->getContentHelper()->readSourceMetadata($submission);
        $this->getLogger()->debug(vsprintf('Searching for Featured Images related to submission = \'%s\'.', [
            $submission->getId(),
        ]));
        if (array_key_exists('_thumbnail_id', $originalMetadata)) {

            if (is_array($originalMetadata['_thumbnail_id'])) {
                $originalMetadata['_thumbnail_id'] = (int)reset($originalMetadata['_thumbnail_id']);
            }

            $targetEntity = $this->getContentHelper()->readTargetContent($submission);
            $this->getLogger()
                ->debug(vsprintf('Sending for translation Featured Image id = \'%s\' related to submission = \'%s\'.', [
                    $originalMetadata['_thumbnail_id'],
                    $submission->getId(),
                ]));

            $attSubmission = $this->fastSendForTranslation(WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT, $submission->getSourceBlogId(), $originalMetadata['_thumbnail_id'], $submission->getTargetBlogId());

            do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $attSubmission);

            $this->getContentHelper()->writeTargetMetadata($submission, ['_thumbnail_id' => $attSubmission->getTargetId()]);
        }
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @throws BlogNotFoundException
     */
    public function prepareRelatedSubmissions(SubmissionEntity $submission)
    {
        $this->getLogger()->info(vsprintf('Searching for related content for submission = \'%s\' for translation', [
            $submission->getId(),
        ]));

        $tagretContentId = $submission->getTargetId();

        $originalEntity = $this->getContentHelper()->readSourceContent($submission);
        $relatedContentTypes = $originalEntity->getRelatedTypes();
        $accumulator = [
            WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY => [],
            WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG => [],
        ];

        try {
            if (!empty($relatedContentTypes)) {
                foreach ($relatedContentTypes as $contentType) {
                    // SM Specific
                    try {
                        $this->processMediaAttachedToWidgetSM($submission, $contentType);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing related media attachments for SM Widgets submission=%s', [$submission->getId()]));
                    }

                    try {
                        $this->processTestimonialAttachedToWidgetSM($submission, $contentType);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing related testimonial for SM Widgets submission=%s', [$submission->getId()]));
                    }

                    try {
                        $this->processTestimonialsAttachedToWidgetSM($submission, $contentType);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing related testimonials for SM Widgets submission=%s', [$submission->getId()]));
                    }

                    //Standard
                    try {
                        $this->processRelatedPage($submission);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing related page for submission=%s', [$submission->getId()]));
                    }
                    try {
                        $this->processRelatedTerm($submission, $contentType, $accumulator);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing related terms for submission=%s', [$submission->getId()]));
                    }
                    try {
                        $this->processRelatedMenu($submission, $contentType, $accumulator);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing related menu for submission=%s', [$submission->getId()]));
                    }
                    try {
                        $this->processMenuRelatedToWidget($submission, $contentType);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing menu related to widget for submission=%s', [$submission->getId()]));
                    }
                    try {
                        $this->processFeaturedImage($submission);
                    } catch (\Exception $e) {
                        $this->getLogger()
                            ->warning(vsprintf('An unhandled exception occurred while processing featured image for submission=%s', [$submission->getId()]));
                    }
                }
            }

            if ($submission->getContentType() !== WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU) {
                $needSwitchBlog = $this->getSiteHelper()->getCurrentBlogId() != $submission->getTargetBlogId();

                if ($needSwitchBlog) {
                    $this->getSiteHelper()->switchBlogId($submission->getTargetBlogId());
                }

                $this->getLogger()
                    ->debug(vsprintf('Preparing to assign accumulator: %s', [var_export($accumulator, true)]));

                foreach ($accumulator as $type => $ids) {
                    $this->getLogger()
                        ->debug(vsprintf('Assigning term (type = \'%s\', ids = \'%s\') to content (type = \'%s\', id = \'%s\') on blog= \'%s\'.', [
                            $type,
                            implode(',', $ids),
                            $submission->getContentType(),
                            $tagretContentId,
                            $submission->getTargetBlogId(),
                        ]));

                    wp_set_post_terms($submission->getTargetId(), $ids, $type);

                }

                if ($needSwitchBlog) {
                    $this->getSiteHelper()->restoreBlogId();
                }
            } else {
                $this->getCustomMenuHelper()
                    ->assignMenuItemsToMenu((int)$submission->getTargetId(), (int)$submission->getTargetBlogId(), $accumulator[WordpressContentTypeHelper::CONTENT_TYPE_NAV_MENU]);
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
     *
     * @return bool
     */
    protected function sendFile(SubmissionEntity $submission, $xmlFileContent)
    {
        $tmp_file = tempnam(sys_get_temp_dir(), '_smartling_temp_');

        file_put_contents($tmp_file, $xmlFileContent);

        $result = $this->getApiWrapper()->uploadContent($submission, '', $tmp_file);

        unlink($tmp_file);

        return $result;
    }

    /**
     * @param SubmissionEntity $entity
     *
     * @return EntityAbstract
     */
    private function getContentIOWrapper(SubmissionEntity $entity)
    {
        return $this->getContentIoFactory()->getMapper($entity->getContentType());
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
            $this->getLogger()->info(vsprintf(self::$MSG_CRON_CHECK, [
                $submission->getId(),
                $submission->getStatus(),
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
                $submission->getTargetBlogId(),
                $submission->getTargetLocale(),
            ]));

            $submission = $this->getApiWrapper()->getStatus($submission);

            $this->getLogger()->info(vsprintf(self::$MSG_CRON_CHECK_RESULT, [
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
                $submission->getTargetLocale(),
                $submission->getApprovedStringCount(),
                $submission->getCompletedStringCount(),
            ]));


            $submission = $this->getSubmissionManager()->storeEntity($submission);
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
        } else {
            $message = vsprintf('Requested SubmissionEntity with id=%s does not exist.', [$id]);

            $this->getLogger()->error($message);
            throw new SmartlingDbException($message);
        }
    }

    /**
     * @param array $items
     *
     * @return array
     * @throws SmartlingDbException
     */
    public function bulkCheckByIds(array $items)
    {
        $results = [];
        foreach ($items as $item) {
            /** @var SubmissionEntity $entity */
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

    /**
     * Forces image thumbnail re-generation
     *
     * @param SubmissionEntity $submission
     *
     * @throws BlogNotFoundException
     */
    private function regenerateTargetThumbnailsBySubmission(SubmissionEntity $submission)
    {

        $this->getLogger()->debug(vsprintf('Starting thumbnails regeneration for blog = \'%s\' attachment id = \'%s\'.', [
            $submission->getTargetBlogId(),
            $submission->getTargetId(),
        ]));

        if (WordpressContentTypeHelper::CONTENT_TYPE_MEDIA_ATTACHMENT !== $submission->getContentType()) {
            return;
        }

        $needBlogSwitch = $submission->getTargetBlogId() !== $this->getSiteHelper()->getCurrentBlogId();

        if ($needBlogSwitch) {
            $this->getSiteHelper()->switchBlogId($submission->getTargetBlogId());
        }

        $originalImage = get_attached_file($submission->getTargetId());

        if (!function_exists('wp_generate_attachment_metadata')) {
            include_once(ABSPATH . 'wp-admin/includes/image.php'); //including the attachment function
        }
        $metadata = wp_generate_attachment_metadata($submission->getTargetId(), $originalImage);


        if (is_wp_error($metadata)) {

            $this->getLogger()
                ->error(vsprintf('Error occurred while regenerating thumbnails for blog=\'%s\' attachment id=\'%s\'. Message:\'%s\'.', [
                    $submission->getTargetBlogId(),
                    $submission->getTargetId(),
                    $metadata->get_error_message(),
                ]));
        }

        if (empty($metadata)) {
            $this->getLogger()
                ->error(vsprintf('Couldn\'t regenerate thumbnails for blog=\'%s\' attachment id=\'%s\'.', [
                    $submission->getTargetBlogId(),
                    $submission->getTargetId(),
                ]));
        }

        wp_update_attachment_metadata($submission->getTargetId(), $metadata);

        if ($needBlogSwitch) {
            $this->getSiteHelper()->restoreBlogId();
        }
    }
}
