<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\JobAbstract;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Queue\Queue;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\AuditLog\Params\CreateRecordParameters;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class PostWidgetController
 *
 * @package Smartling\WP\Controller
 */
class PostBasedWidgetControllerStd extends WPAbstract implements WPHookInterface
{
    use DetectContentChangeTrait;

    const WIDGET_NAME = 'smartling_connector_widget';
    const WIDGET_DATA_NAME = 'smartling';
    const CONNECTOR_NONCE = 'smartling_connector_nonce';

    private ApiWrapperInterface $apiWrapper;
    protected $servedContentType = 'undefined';
    protected $needSave = 'Need to have title';
    protected $noOriginalFound = 'No original post found';
    protected $abilityNeeded = 'edit_post';

    const RESPONSE_AJAX_STATUS_FAIL = 'FAIL';
    const RESPONSE_AJAX_STATUS_SUCCESS = 'SUCCESS';

    const ERROR_KEY_FIELD_MISSING = 'field.missing';
    const ERROR_MSG_FIELD_MISSING = 'Required field \'%s\' is missing.';

    const ERROR_KEY_NO_PROFILE_FOUND = 'profile.not.found';
    const ERROR_MSG_NO_PROFILE_FOUND = 'No suitable configuration profile found.';

    const ERROR_KEY_TARGET_BLOG_EMPTY = 'no.target';
    const ERROR_MSG_TARGET_BLOG_EMPTY = 'No target blog selected.';

    const ERROR_KEY_NO_CONTENT = 'no.content';
    const ERROR_MSG_NO_CONTENT = 'No source content selected.';


    const ERROR_KEY_INVALID_BLOG = 'invalid.blog';
    const ERROR_MSG_INVALID_BLOG = 'Invalid blog value.';

    const ERROR_KEY_BATCH_FETCH_FAILED = 'fail.fetch.batch';
    const ERROR_MSG_BATCH_FETCH_FAILED = 'Failed fetching batch for job.';

    const ERROR_KEY_TYPE_MISSING = 'content.type.missing';
    const ERROR_MSG_TYPE_MISSING = 'Source content-type missing.';

    private $mutedTypes = [
        'attachment',
    ];

    public function __construct(ApiWrapperInterface $apiWrapper, LocalizationPluginProxyInterface $connector, PluginInfo $pluginInfo, EntityHelper $entityHelper, SubmissionManager $manager, Cache $cache)
    {
        parent::__construct($connector, $pluginInfo, $entityHelper, $manager, $cache);
        $this->apiWrapper = $apiWrapper;
    }

    private function isMuted()
    {
        return in_array($this->getServedContentType(), $this->mutedTypes, true);
    }

    /**
     * @return string
     */
    public function getAbilityNeeded()
    {
        return $this->abilityNeeded;
    }

    /**
     * @param string $abilityNeeded
     */
    public function setAbilityNeeded($abilityNeeded)
    {
        $this->abilityNeeded = $abilityNeeded;
    }


    /**
     * @return string
     */
    public function getServedContentType()
    {
        return $this->servedContentType;
    }

    /**
     * @param string $servedContentType
     */
    public function setServedContentType($servedContentType)
    {
        $this->servedContentType = $servedContentType;
    }

    /**
     * @return string
     */
    public function getNoOriginalFound()
    {
        return $this->noOriginalFound;
    }

    /**
     * @param string $noOriginalFound
     */
    public function setNoOriginalFound($noOriginalFound)
    {
        $this->noOriginalFound = $noOriginalFound;
    }

    use CommonLogMessagesTrait;


    public function ajaxDownloadHandler()
    {
        $logSubmissions = [];
        if (array_key_exists('submissionIds', $_POST)) {
            $profile = null;
            $submissionIds = explode(',', $_POST['submissionIds']);
            foreach ($submissionIds as $submissionId) {
                $submission = $this->getManager()->getEntityById($submissionId);
                if ($submission !== null) {
                    if ($profile === null) {
                        $profile = $this->getEntityHelper()->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId());
                    }
                    $logSubmissions[] = [
                        'sourceBlogId' => $submission->getSourceBlogId(),
                        'sourceId' => $submission->getSourceId(),
                        'submissionId' => $submission->getId(),
                        'targetBlogId' => $submission->getTargetBlogId(),
                        'targetId' => $submission->getTargetId(),
                    ];
                }
                $this->getCore()->getQueue()->enqueue([$submissionId], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
            }
            $result = [];
            try {
                do_action(DownloadTranslationJob::JOB_HOOK_NAME, JobAbstract::SOURCE_USER);
                $result['status'] = self::RESPONSE_AJAX_STATUS_SUCCESS;
            } catch (\Exception $e) {
                $result['status'] = self::RESPONSE_AJAX_STATUS_FAIL;
                $result['message'] = $e->getMessage();
            }
            if ($profile !== null) {
                $this->apiWrapper->createAuditLogRecord($profile, CreateRecordParameters::ACTION_TYPE_DOWNLOAD, 'User request to download submissions', ['submissions' => $logSubmissions]);
            } else {
                /** @noinspection JsonEncodingApiUsageInspection */
                $this->getLogger()->notice('No profile was found for submissions, no audit log created, submissions=' . json_encode($logSubmissions));
            }
            wp_send_json($result);
        }
    }

    /**
     * @param int $blogId
     * @return bool
     */
    private function validateTargetBlog($blogId)
    {
        $blogs = $this->getEntityHelper()->getSiteHelper()->listBlogIdsFlat();
        return in_array((int)$blogId, $blogs, true);
    }

    public function ajaxUploadHandler()
    {
        $result = [];

        $data = &$_POST;

        foreach (['blogs', 'job', 'content'] as $requiredField) {
            $continue = array_key_exists($requiredField, $data);
            if (!$continue) {
                $msg = vsprintf(self::ERROR_MSG_FIELD_MISSING, [$requiredField]);
                $this->getLogger()->error(
                    vsprintf('Failed adding content to upload queue: %s for %s', [$msg, var_export($_POST, true)])
                );
                $result = [
                    'status' => 'FAIL',
                    'key' => self::ERROR_KEY_FIELD_MISSING,
                    'message' => $msg,
                ];
            }
        }

        $profile = ArrayHelper::first($this->getProfiles());

        /**
         * checking profiles
         */
        if ($continue && !$profile) {
            $this->getLogger()->error(
                vsprintf(
                    'Failed adding content to upload queue: %s for %s',
                    [self::ERROR_MSG_NO_PROFILE_FOUND, var_export($_POST, true)])
            );

            $result = [
                'status' => 'FAIL',
                'key' => self::ERROR_KEY_NO_PROFILE_FOUND,
                'message' => self::ERROR_MSG_NO_PROFILE_FOUND,
            ];

            $continue = false;
        }

        /**
         * checking 'blogs'
         */
        if ($continue) {

            $data['blogs'] = explode(',', $data['blogs']);

            if (empty($data['blogs'])) {

                $this->getLogger()->error(
                    vsprintf(
                        'Failed adding content to upload queue: %s for %s',
                        [self::ERROR_MSG_TARGET_BLOG_EMPTY, var_export($_POST, true)])
                );

                $result = [
                    'status' => 'FAIL',
                    'key' => self::ERROR_KEY_TARGET_BLOG_EMPTY,
                    'message' => self::ERROR_MSG_TARGET_BLOG_EMPTY,
                ];
                $continue = false;
            } else {
                foreach ($data['blogs'] as $blogId) {
                    $continue &= $this->validateTargetBlog($blogId);

                    if (!$continue) {
                        $this->getLogger()->error(
                            vsprintf(
                                'Failed adding content to upload queue: %s for %s',
                                [self::ERROR_MSG_INVALID_BLOG, var_export($_POST, true)])
                        );

                        $result = [
                            'status' => 'FAIL',
                            'key' => self::ERROR_KEY_INVALID_BLOG,
                            'message' => self::ERROR_MSG_INVALID_BLOG,
                        ];
                    }
                }
            }
        }

        if ($continue) {
            $data['content']['id'] = explode(',', $data['content']['id']);

            if (empty($data['content']['id'])) {

                $this->getLogger()->error(
                    vsprintf(
                        'Failed adding content to upload queue: %s for %s. Translation aborted.',
                        [self::ERROR_MSG_NO_CONTENT, var_export($_POST, true)])
                );
                $result = [
                    'status' => 'FAIL',
                    'key' => self::ERROR_KEY_NO_CONTENT,
                    'message' => self::ERROR_MSG_NO_CONTENT,
                ];
                $continue = false;
            } elseif (!array_key_exists('type', $data['content'])) {

                $this->getLogger()->error(
                    vsprintf(
                        'Failed adding content to upload queue: %s for %s. Translation aborted.',
                        [self::ERROR_MSG_TYPE_MISSING, var_export($_POST, true)])
                );
                $result = [
                    'status' => 'FAIL',
                    'key' => self::ERROR_KEY_TYPE_MISSING,
                    'message' => self::ERROR_MSG_TYPE_MISSING,
                ];
                $continue = false;
            }
        }

        $batchUid = null;

        if ($continue) {
            try {
                $jobName = $data['job']['name'];
                $batchUid = $this->getCore()->getApiWrapper()->retrieveBatch(
                    $profile,
                    $data['job']['id'],
                    'true' === $data['job']['authorize'],
                    [
                        'name' => $jobName,
                        'description' => $data['job']['description'],
                        'dueDate' => [
                            'date' => $data['job']['dueDate'],
                            'timezone' => $data['job']['timezone'],
                        ],
                    ]
                );
            } catch (\Exception $e) {
                $this->getLogger()->error(
                    vsprintf(
                        'Failed adding content to upload queue: %s for %s. Translation aborted.',
                        [self::ERROR_MSG_BATCH_FETCH_FAILED, var_export($_POST, true)])
                );
                $result = [
                    'status' => 'FAIL',
                    'key' => self::ERROR_KEY_BATCH_FETCH_FAILED,
                    'message' => self::ERROR_MSG_BATCH_FETCH_FAILED,
                ];
            }
        }


        if ($continue) {
            $sourceIds = &$data['content']['id'];
            $contentType = &$data['content']['type'];
            $sourceBlog = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();

            /**
             * Walk through target blogs
             */
            foreach ($data['blogs'] as $targetBlogId) {
                /**
                 * Walk source content ids
                 */
                foreach ($sourceIds as $sourceId) {
                    try {
                        $jobInfo = new JobEntityWithBatchUid($batchUid, $jobName, $data['job']['id'], $profile->getProjectId());
                        if ($this->getCore()->getTranslationHelper()->isRelatedSubmissionCreationNeeded($contentType, $sourceBlog, (int)$sourceId, (int)$targetBlogId)) {
                            $submission = $this->getCore()->getTranslationHelper()->tryPrepareRelatedContent($contentType, $sourceBlog, (int)$sourceId, (int)$targetBlogId, $jobInfo);
                        } else {
                            $submission = $this->getCore()->getTranslationHelper()->getExistingSubmissionOrCreateNew($contentType, $sourceBlog, (int)$sourceId, (int)$targetBlogId, $jobInfo);
                        }

                        if (0 < $submission->getId()) {
                            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                            $submission->setBatchUid($jobInfo->getBatchUid());
                            $submission->setJobInfo($jobInfo->getJobInformationEntity());
                            $submission = $this->getCore()->getSubmissionManager()->storeEntity($submission);
                        }

                        $this->getLogger()->info(
                            vsprintf(
                                static::$MSG_UPLOAD_ENQUEUE_ENTITY_JOB,
                                [
                                    $contentType,
                                    $sourceBlog,
                                    $sourceId,
                                    $targetBlogId,
                                    $submission->getTargetLocale(),
                                    $data['job']['id'],
                                    $submission->getBatchUid(),
                                ]
                            ));
                    } catch (\Exception $e) {
                        $this->getLogger()->error(sprintf(
                            "Failed adding '%s' from blog='%s', id='%s' for target blog id='%s' for translation queue with batchUid='%s': %s",
                            $contentType,
                            $sourceBlog,
                            $sourceId,
                            $targetBlogId,
                            $batchUid,
                            $e->getMessage()
                        ));
                    }
                }
            }

            $result = [
                'status' => self::RESPONSE_AJAX_STATUS_SUCCESS,
            ];
        }

        wp_send_json($result);
    }

    public function register(): void
    {
        if (!DiagnosticsHelper::isBlocked() && !$this->isMuted() && current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            add_action('add_meta_boxes', [$this, 'box']);
            add_action('save_post', [$this, 'save']); // old logic 2 be refactored
            add_action('wp_ajax_' . 'smartling_force_download_handler', [$this, 'ajaxDownloadHandler']);
            add_action('wp_ajax_' . 'smartling_upload_handler', [$this, 'ajaxUploadHandler']);
        }
    }

    /**
     * @var SmartlingCore
     */
    private $core;

    /**
     * @return SmartlingCore
     */
    private function getCore()
    {
        if (!($this->core instanceof SmartlingCore)) {
            $this->core = Bootstrap::getContainer()->get('entrypoint');
        }

        return $this->core;
    }

    /**
     * add_meta_boxes hook
     *
     * @param string $post_type
     */
    public function box($post_type)
    {
        $post_types = [$this->servedContentType];

        if (in_array($post_type, $post_types, true) &&
            current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)
        ) {
            add_meta_box(
                self::WIDGET_NAME,
                __('Smartling Widget'),
                [$this, 'preView'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * @param $post
     */
    public function preView($post)
    {
        wp_nonce_field(self::WIDGET_NAME, self::CONNECTOR_NONCE);
        if ($post && $post->post_title && '' !== $post->post_title) {
            try {
                $eh = $this->getEntityHelper();
                $currentBlogId = $eh->getSiteHelper()->getCurrentBlogId();
                $profile = $eh->getSettingsManager()->findEntityByMainLocale($currentBlogId);
                $submissionManager = $this->getManager();

                if (0 < count($profile)) {
                    $submissions = $submissionManager
                                        ->find([
                                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $currentBlogId,
                                            SubmissionEntity::FIELD_SOURCE_ID => $post->ID,
                                            SubmissionEntity::FIELD_CONTENT_TYPE => $this->servedContentType,
                                        ]);

                    $this->view([
                            'submissions' => $submissions,
                            'post' => $post,
                            'profile' => ArrayHelper::first($profile),
                        ]
                    );
                    if (GlobalSettingsManager::isGenerateLockIdsEnabled()) {
                        wp_enqueue_script(
                            'smartling-block-locking',
                            $this->getPluginInfo()->getUrl() . 'js/smartling-block-locking.js',
                            ['wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor'],
                            $this->getPluginInfo()->getVersion(),
                            true,
                        );
                    }
                } else {
                    echo '<p>' . __('No suitable configuration profile found.') . '</p>';
                }
                if (count($submissionManager->find([
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $currentBlogId,
                    SubmissionEntity::FIELD_TARGET_ID => $post->ID,
                    ], 1)) > 0) {
                    wp_enqueue_script(
                        'smartling-block-sidebar',
                        $this->getPluginInfo()->getUrl() . 'js/smartling-block-sidebar.js',
                        ['wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor'],
                        $this->getPluginInfo()->getVersion(),
                        true
                    );
                }
            } catch (SmartlingDbException $e) {
                $message = 'Failed to search for the original post. No source post found for blog %s, post %s. Hiding widget';
                $this->getLogger()
                     ->warning(
                         vsprintf($message, [
                             $this->getEntityHelper()
                                  ->getSiteHelper()
                                  ->getCurrentBlogId(),
                             $post->ID,
                         ])
                     );
                echo '<p>' . __($this->noOriginalFound) . '</p>';
            } catch (\Exception $e) {
                $this->getLogger()
                     ->error($e->getMessage() . '[' . $e->getFile() . ':' . $e->getLine() . ']');
            }
        } else {
            echo '<p>' . __($this->needSave) . '</p>';
        }
    }

    /**
     * @param $post_id
     *
     * @return bool
     */
    private function runValidation($post_id)
    {
        $this->getLogger()->debug(vsprintf('Validating post id = \'%s\' saving', [$post_id]));
        if (!array_key_exists(self::CONNECTOR_NONCE, $_POST)) {
            $this->getLogger()->debug(vsprintf('Validation failed: no nonce exists', []));

            return false;
        }

        $nonce = $_POST[self::CONNECTOR_NONCE];

        if (!wp_verify_nonce($nonce, self::WIDGET_NAME)) {
            $this->getLogger()->debug(vsprintf('Validation failed: invalid nonce exists', []));

            return false;
        }

        if (defined('DOING_AUTOSAVE') && true === DOING_AUTOSAVE) {
            $this->getLogger()->debug(vsprintf('Validation failed: that is just autosave.', []));

            return false;
        }

        return $this->isAllowedToSave($post_id);
    }

    /**
     * @param $post_id
     *
     * @return bool
     */
    protected function isAllowedToSave($post_id)
    {
        $result = current_user_can($this->getAbilityNeeded(), $post_id);

        if (false === $result) {
            $this->getLogger()
                 ->debug(vsprintf('Validation failed: current user doesn\'t have enough rights save the post', []));
        }

        return $result;
    }

    /**
     * @param mixed $post_id
     */
    public function save($post_id): void
    {
        $this->getLogger()->debug("Usage: class='" . __CLASS__ . "', function='" . __FUNCTION__ . "'");
        remove_action('save_post', [$this, 'save']);
        if (!array_key_exists('post_type', $_POST)) {
            return;
        }

        if ($this->servedContentType === $_POST['post_type']) {

            $this->getLogger()->debug(
                vsprintf('Entering post save hook. post_id = \'%s\', blog_id = \'%s\'',
                    [
                        $post_id,
                        $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId(),
                    ])
            );
            // Handle case when a revision is being saved. Get post_id by
            // revision id.
            if ($parent_id = wp_is_post_revision($post_id)) {
                $post_id = $parent_id;
            }

            $sourceBlog = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();
            $originalId = (int)$post_id;
            $this->getLogger()->debug(vsprintf('Detecting changes for \'%s\' id=%d',
                [$this->servedContentType, $post_id]));
            $this->detectChange($sourceBlog, $originalId, $this->servedContentType);

            if (false === $this->runValidation($post_id)) {
                return;
            }
            $this->getLogger()->debug(vsprintf('Validation completed for \'%s\' id=%d',
                [$this->servedContentType, $post_id]));

            if (!array_key_exists(self::WIDGET_DATA_NAME, $_POST)) {
                $this->getLogger()
                     ->debug(vsprintf('Validation failed: no smartling info while saving. Ignoring', [$post_id]));

                return;
            }

            $data = $_POST[self::WIDGET_DATA_NAME];

            $this->getLogger()->debug(vsprintf('got POST data: %s', [var_export($_POST, true)]));

            if (null !== $data && array_key_exists('locales', $data)) {
                $locales = [];
                if (is_array($data['locales'])) {
                    foreach ($data['locales'] as $_locale) {
                        if (array_key_exists('enabled', $_locale) && 'on' === $_locale['enabled']) {
                            $locales[] = (int)$_locale['blog'];
                        }
                    }
                } elseif (is_string($data['locales'])) {
                    $locales = explode(',', $data['locales']);
                } else {
                    return;
                }
                $this->getLogger()->debug(vsprintf('Finished parsing locales: %s', [var_export($locales, true)]));
                $core = $this->getCore();
                $translationHelper = $core->getTranslationHelper();
                if (array_key_exists('sub', $_POST) && count($locales) > 0) {
                    switch ($_POST['sub']) {
                        case 'Upload':
                            $this->getLogger()->debug('Upload case detected.');
                            if (0 < count($locales)) {
                                $wrapper = $this->getCore()->getApiWrapper();
                                $profile = ArrayHelper::first($this->getProfiles());

                                if (!$profile) {
                                    $this->getLogger()->error('No suitable configuration profile found.');

                                    return;
                                }
                                $this->getLogger()->debug(vsprintf('Retrieving batch for jobId=%s', [$data['jobId']]));

                                try {
                                    $batchUid = $wrapper->retrieveBatch($profile, $data['jobId'],
                                        'true' === $data['authorize'], [
                                            'name' => $data['jobName'],
                                            'description' => $data['jobDescription'],
                                            'dueDate' => [
                                                'date' => $data['jobDueDate'],
                                                'timezone' => $data['timezone'],
                                            ],
                                        ]);
                                } catch (\Exception $e) {
                                    $this
                                        ->getLogger()
                                        ->error(
                                            vsprintf(
                                                'Failed retrieving batch for job %s. Translation aborted.',
                                                [
                                                    var_export($_POST['jobId'], true),
                                                ]
                                            )
                                        );
                                    return;
                                }

                                $jobInfo = new JobEntityWithBatchUid($batchUid, $data['jobName'], $data['jobId'], $profile->getProjectId());
                                foreach ($locales as $blogId) {
                                    if ($translationHelper->isRelatedSubmissionCreationNeeded($this->servedContentType, $sourceBlog, $originalId, (int)$blogId)) {
                                        $submission = $translationHelper->tryPrepareRelatedContent($this->servedContentType, $sourceBlog, $originalId, (int)$blogId, $jobInfo);
                                    } else {
                                        $submission = $translationHelper->getExistingSubmissionOrCreateNew($this->servedContentType, $sourceBlog, $originalId, (int)$blogId, $jobInfo);
                                    }

                                    if (0 < $submission->getId()) {
                                        $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                                        $submission->setBatchUid($jobInfo->getBatchUid());
                                        $submission->setJobInfo($jobInfo->getJobInformationEntity());
                                        $submission = $core->getSubmissionManager()->storeEntity($submission);
                                    }

                                    $this->getLogger()->info(
                                        vsprintf(
                                            static::$MSG_UPLOAD_ENQUEUE_ENTITY_JOB,
                                            [
                                                $this->servedContentType,
                                                $sourceBlog,
                                                $originalId,
                                                (int)$blogId,
                                                $submission->getTargetLocale(),
                                                $data['jobId'],
                                                $submission->getBatchUid(),
                                            ]
                                        ));
                                }

                                /**
                                 * $this->getLogger()->debug('Triggering Upload Job.');
                                 * do_action(UploadJob::JOB_HOOK_NAME);
                                 */

                            } else {
                                $this->getLogger()->debug('No locales found.');
                            }
                            break;
                        case 'Download':
                            foreach ($locales as $targetBlogId) {
                                $submissions = $this->getManager()
                                                    ->find(
                                                        [
                                                            SubmissionEntity::FIELD_SOURCE_ID=> $originalId,
                                                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlog,
                                                            SubmissionEntity::FIELD_CONTENT_TYPE => $this->servedContentType,
                                                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                                                        ]
                                                    );

                                if (0 < count($submissions)) {
                                    $submission = ArrayHelper::first($submissions);

                                    $this->getLogger()
                                         ->info(
                                             vsprintf(
                                                 static::$MSG_DOWNLOAD_ENQUEUE_ENTITY,
                                                 [
                                                     $submission->getId(),
                                                     $submission->getStatus(),
                                                     $this->servedContentType,
                                                     $sourceBlog,
                                                     $originalId,
                                                     $submission->getTargetBlogId(),
                                                     $submission->getTargetLocale(),
                                                 ]
                                             )
                                         );

                                    $core->getQueue()
                                         ->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
                                }
                            }
                            $this->getLogger()->debug(vsprintf('Initiating Download Job', []));
                            do_action(DownloadTranslationJob::JOB_HOOK_NAME);
                            break;
                        default:
                            $this->getLogger()->debug(vsprintf('got Unknown action: \'%s\'', [$_POST['sub']]));
                    }
                } else {
                    $this->getLogger()->debug('No smartling action found.');
                }
            } else {
                $this->getLogger()->debug('Seems that no data to process.');
            }
            add_action('save_post', [$this, 'save']);
        }
    }
}
