<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\CommonLogMessagesTrait;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\AuditLog\Params\CreateRecordParameters;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class PostBasedWidgetControllerStd extends WPAbstract implements WPHookInterface
{
    use DetectContentChangeTrait;

    private const WIDGET_NAME = 'smartling_connector_widget';
    public const WIDGET_DATA_NAME = 'smartling';
    private const CONNECTOR_NONCE = 'smartling_connector_nonce';

    protected string $servedContentType = 'undefined';
    protected string $needSave = 'Need to have title';
    protected string $noOriginalFound = 'No original post found';
    protected string $abilityNeeded = 'edit_post';

    private const RESPONSE_AJAX_STATUS_FAIL = 'FAIL';
    private const RESPONSE_AJAX_STATUS_SUCCESS = 'SUCCESS';

    private const ERROR_KEY_FIELD_MISSING = 'field.missing';
    private const ERROR_MSG_FIELD_MISSING = 'Required field \'%s\' is missing.';

    private const ERROR_KEY_NO_PROFILE_FOUND = 'profile.not.found';
    private const ERROR_MSG_NO_PROFILE_FOUND = 'No suitable configuration profile found.';

    private const ERROR_KEY_TARGET_BLOG_EMPTY = 'no.target';
    private const ERROR_MSG_TARGET_BLOG_EMPTY = 'No target blog selected.';

    private const ERROR_KEY_NO_CONTENT = 'no.content';
    private const ERROR_MSG_NO_CONTENT = 'No source content selected.';


    private const ERROR_KEY_INVALID_BLOG = 'invalid.blog';
    private const ERROR_MSG_INVALID_BLOG = 'Invalid blog value.';

    private const ERROR_KEY_TYPE_MISSING = 'content.type.missing';
    private const ERROR_MSG_TYPE_MISSING = 'Source content-type missing.';

    private array $mutedTypes = [
        'attachment',
    ];

    public function __construct(
        private ApiWrapperInterface $apiWrapper,
        LocalizationPluginProxyInterface $connector,
        PluginInfo $pluginInfo,
        SettingsManager $settingsManager,
        SiteHelper $siteHelper,
        private SubmissionManager $submissionManager,
        Cache $cache,
    ) {
        parent::__construct($connector, $pluginInfo, $settingsManager, $siteHelper, $submissionManager, $cache);
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

    public function ajaxDownloadHandler(): void
    {
        $logSubmissions = [];
        $result = ['status' => self::RESPONSE_AJAX_STATUS_SUCCESS];
        $submissions = [];
        if (array_key_exists('submissionIds', $_POST)) {
            $profile = null;
            $submissionIds = explode(',', $_POST['submissionIds']);
            foreach ($submissionIds as $submissionId) {
                $submission = $this->getManager()->getEntityById($submissionId);
                if ($submission !== null) {
                    $submissions[] = $submission;
                    if ($profile === null) {
                        $profile = $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
                    }
                    $logSubmissions[] = [
                        'sourceBlogId' => $submission->getSourceBlogId(),
                        'sourceId' => $submission->getSourceId(),
                        'submissionId' => $submission->getId(),
                        'targetBlogId' => $submission->getTargetBlogId(),
                        'targetId' => $submission->getTargetId(),
                    ];
                }
            }

            if ($profile !== null) {
                try {
                    $requestDescription = 'User request to download submissions';
                    $this->apiWrapper->createAuditLogRecord($profile, CreateRecordParameters::ACTION_TYPE_DOWNLOAD, $requestDescription, ['submissions' => $logSubmissions]);
                } catch (\Exception) {
                    $this->getLogger()->error(sprintf('Failed to create audit log record actionType=%s, requestDescription="%s", submissions="%s"', CreateRecordParameters::ACTION_TYPE_DOWNLOAD, $requestDescription, json_encode($logSubmissions)));
                }
            } else {
                /** @noinspection JsonEncodingApiUsageInspection */
                $this->getLogger()->notice('No profile was found for submissions, no audit log created, submissions=' . json_encode($logSubmissions));
            }

            foreach ($submissions as $submission) {
                try {
                    do_action(ExportedAPI::ACTION_SMARTLING_DOWNLOAD_TRANSLATION, $submission);
                } catch (\Exception $e) {
                    $result['status'] = self::RESPONSE_AJAX_STATUS_FAIL;
                    $result['message'] = $e->getMessage();
                    break;
                }
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
        $blogs = $this->siteHelper->listBlogIdsFlat();
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
                if (!empty($jobName)) {
                    $description = empty($data['job']['description'] ?? '') ? null : $data['job']['description'];
                    $dueDate = null;

                    if (!empty($data['job']['dueDate']['date'] ?? '') && !empty($data['job']['dueDate']['timezone'] ?? '')) {
                        $dueDate = \DateTime::createFromFormat(DateTimeHelper::DATE_TIME_FORMAT_JOB, $data['job']['dueDate']['date'], new \DateTimeZone($data['job']['dueDate']['timezone']));
                        $dueDate->setTimeZone(new \DateTimeZone('UTC'));
                    }

                    $this->getCore()->getApiWrapper()->updateJob($profile, $data['job']['id'], $jobName, $description, $dueDate);

                    $this->getLogger()->info(sprintf('Updated jobId=%s: %s', $data['job']['id'], json_encode($data['job'], JSON_THROW_ON_ERROR)));
                }
            } catch (\Exception $e) {
                $this->getLogger()->error(
                    sprintf(
                        'Failed adding content to upload queue: %s for %s. Translation aborted. Reason: %s',
                        'Job update failed', var_export($_POST, true), $e->getMessage())
                );
                $result = [
                    'status' => self::RESPONSE_AJAX_STATUS_FAIL,
                    'key' => 'fail.update.job',
                    'message' => 'Job update failed',
                ];
            }
        }


        if ($continue) {
            $sourceIds = &$data['content']['id'];
            $contentType = &$data['content']['type'];
            $sourceBlog = $this->siteHelper->getCurrentBlogId();

            foreach ($data['blogs'] as $targetBlogId) {
                foreach ($sourceIds as $sourceId) {
                    try {
                        $jobInfo = new JobEntityWithBatchUid('', $jobName, $data['job']['id'], $profile->getProjectId());
                        if ($this->getCore()->getTranslationHelper()->isRelatedSubmissionCreationNeeded($contentType, $sourceBlog, (int)$sourceId, (int)$targetBlogId)) {
                            $submission = $this->getCore()->getTranslationHelper()->tryPrepareRelatedContent($contentType, $sourceBlog, (int)$sourceId, (int)$targetBlogId, $jobInfo);
                        } else {
                            $submission = $this->getCore()->getTranslationHelper()->getExistingSubmissionOrCreateNew($contentType, $sourceBlog, (int)$sourceId, (int)$targetBlogId, $jobInfo);
                        }

                        if (0 < $submission->getId()) {
                            $submission->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW);
                            $submission->setJobInfo($jobInfo->getJobInformationEntity());
                            $submission = $this->submissionManager->storeEntity($submission);
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
                                ]
                            ));
                    } catch (\Exception $e) {
                        $this->getLogger()->error(sprintf(
                            "Failed adding '%s' from blog='%s', id='%s' for target blog id='%s' for translation queue with jobUid='%s': %s",
                            $contentType,
                            $sourceBlog,
                            $sourceId,
                            $targetBlogId,
                            $data['job']['id'],
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
                $currentBlogId = $this->siteHelper->getCurrentBlogId();
                $profile = $this->settingsManager->findEntityByMainLocale($currentBlogId);
                if (0 < count($profile)) {
                    $submissions = $this->submissionManager
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
                } else {
                    echo '<p>' . __('No suitable configuration profile found.') . '</p>';
                }
                if (count($this->submissionManager->find([
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
                             $this->siteHelper->getCurrentBlogId(),
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

    public function save(mixed $post_id, mixed $post = null, mixed $update = null): void
    {
        $currentBlogId = $this->siteHelper->getCurrentBlogId();
        $this->getLogger()->debug(sprintf(
            'Processing save hook postData="%s", postId="%s", post="%s", update="%s", currentBlogId=%d',
            json_encode($_POST),
            json_encode($post_id),
            json_encode($post),
            json_encode($update),
            $currentBlogId,
        ));
        remove_action('save_post', [$this, 'save']);
        if (!array_key_exists('post_type', $_POST)) {
            return;
        }

        if ($this->servedContentType === $_POST['post_type']) {

            $this->getLogger()->debug(
                vsprintf('Entering post save hook. post_id = \'%s\', blog_id = \'%s\'',
                    [
                        $post_id,
                        $currentBlogId,
                    ])
            );
            // Handle case when a revision is being saved. Get post_id by
            // revision id.
            if ($parent_id = wp_is_post_revision($post_id)) {
                $this->getLogger()->debug("Saving revision, postId=$post_id, parentId=$parent_id");
                $post_id = $parent_id;
            }

            $originalId = (int)$post_id;
            $this->getLogger()->debug(vsprintf('Detecting changes for \'%s\' id=%d',
                [$this->servedContentType, $post_id]));
            $this->detectChange($currentBlogId, $originalId, $this->servedContentType);

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

            if (($_POST[self::WIDGET_DATA_NAME]['locales'] ?? null) !== null) {
                $this->getLogger()->warning("Download or upload skipped. Revert code to revision 105ae9db6e11a64bf7620c0f421ee41c57271f83");
            }
            add_action('save_post', [$this, 'save']);
        }
    }
}
