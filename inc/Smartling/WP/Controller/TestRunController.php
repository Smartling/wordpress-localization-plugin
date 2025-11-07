<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\AdminNoticesHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\TestRunHelper;
use Smartling\Jobs\JobAbstract;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\UploadJob;
use Smartling\Models\JobInformation;
use Smartling\Models\TestRunViewData;
use Smartling\Models\UserTranslationRequest;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Jobs\JobStatus;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TestRunController extends WPAbstract implements WPHookInterface
{
    private const TEST_RUN_JOB_NAME = 'Test Run Job';

    public const ACTION_CLEAR_FLAG = 'clear_flag';
    public const SLUG = 'smartling_test_run';

    private ContentRelationsDiscoveryService $contentRelationDiscoveryService;
    private ApiWrapperInterface $apiWrapper;
    private SubmissionManager $submissionManager;
    private int $uploadCronInterval;

    public function __construct(
        PluginInfo $pluginInfo,
        LocalizationPluginProxyInterface $localizationPluginProxy,
        SiteHelper $siteHelper,
        SubmissionManager $submissionManager,
        Cache $cache,
        ContentRelationsDiscoveryService $contentRelationDiscoveryService,
        ApiWrapperInterface $apiWrapper,
        SettingsManager $settingsManager,
        string $uploadCronInterval
    ) {
        parent::__construct($localizationPluginProxy, $pluginInfo, $settingsManager, $siteHelper, $submissionManager, $cache);
        $this->apiWrapper = $apiWrapper;
        $this->contentRelationDiscoveryService = $contentRelationDiscoveryService;
        $this->submissionManager = $submissionManager;
        if (!preg_match('~^\d+m$~', $uploadCronInterval)) {
            throw new SmartlingConfigException('Upload job cron interval must be specified in minutes (e. g. 5m), with no extra symbols');
        }
        $this->uploadCronInterval = ((int)substr($uploadCronInterval, 0, - 1)) * 60;
    }

    public function buildViewData(): TestRunViewData
    {
        global $wpdb;
        $testBlogId = SimpleStorageHelper::get(TestRunHelper::TEST_RUN_BLOG_ID_SETTING_NAME);
        $new = $inProgress = $completed = $failed = 0;
        if ($testBlogId !== null) {
            $testBlogId = (int)$testBlogId;
            $condition = ConditionBlock::getConditionBlock();
            $condition->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, SubmissionEntity::FIELD_TARGET_BLOG_ID, [$testBlogId]));
            $new = (int)$wpdb->get_row($this->submissionManager->buildCountQuery(null, SubmissionEntity::SUBMISSION_STATUS_NEW, null, $condition), ARRAY_A)['cnt'];
            $inProgress = (int)$wpdb->get_row($this->submissionManager->buildCountQuery(null, SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS, null, $condition), ARRAY_A)['cnt'];
            $completed = (int)$wpdb->get_row($this->submissionManager->buildCountQuery(null, SubmissionEntity::SUBMISSION_STATUS_COMPLETED, null, $condition), ARRAY_A)['cnt'];
            $failed = (int)$wpdb->get_row($this->submissionManager->buildCountQuery(null, SubmissionEntity::SUBMISSION_STATUS_FAILED, null, $condition), ARRAY_A)['cnt'];
        }
        if ($new + $inProgress + $completed + $failed === 0 && $testBlogId !== null) {
            AdminNoticesHelper::addWarning('A target blog for test run was selected, but no submissions were added. Please retry test run after adding posts and pages to the source blog');
        }

        return new TestRunViewData(
            $this->getBlogs(),
            $testBlogId,
            $new,
            $inProgress,
            $completed,
            $failed,
            SimpleStorageHelper::get(UploadJob::JOB_HOOK_NAME . JobAbstract::LAST_FINISH_SUFFIX) ?? 0,
            $this->uploadCronInterval
        );
    }

    public function wp_enqueue(): void
    {
        wp_enqueue_script(
            $this->getPluginInfo()->getName() . 'admin',
            $this->getPluginInfo()->getUrl() . 'js/smartling-connector-admin.js', ['jquery'],
            $this->getPluginInfo()->getVersion(),
            false,
        );
        wp_register_style(
            $this->getPluginInfo()->getName(),
            $this->getPluginInfo()->getUrl() . 'css/smartling-connector-admin.css', [],
            $this->getPluginInfo()->getVersion(),
        );
        wp_enqueue_style($this->getPluginInfo()->getName());
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('wp_ajax_smartling_test_run', [$this, 'testRun']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Test run',
            'Test run',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP,
            self::SLUG,
            [$this, 'widget']
        );
    }

    public function widget(): void
    {
        if (($_POST['action'] ?? "") === self::ACTION_CLEAR_FLAG) {
            SimpleStorageHelper::drop(TestRunHelper::TEST_RUN_BLOG_ID_SETTING_NAME);
        }
        $viewData = $this->buildViewData();
        if ($viewData->getNew() + $viewData->getInProgress() + $viewData->getCompleted() + $viewData->getFailed() === 0) {
            $this->getLogger()->notice('A blog was selected for test run, but no entries uploaded, clearing test run blog');
            SimpleStorageHelper::drop(TestRunHelper::TEST_RUN_BLOG_ID_SETTING_NAME);
            $viewData = $this->buildViewData();
        }
        $this->view($viewData);
    }

    public function getBlogs(): array
    {
        $blogs = [];
        $currentBlogId = $this->siteHelper->getCurrentBlogId();
        foreach ($this->siteHelper->listBlogs($this->siteHelper->getCurrentSiteId()) as $blogId) {
            if ($currentBlogId !== $blogId && count($this->submissionManager->find([SubmissionEntity::FIELD_TARGET_BLOG_ID => $blogId])) === 0) {
                $blogs[$blogId] = $this->siteHelper->getBlogLabelById($this->localizationPluginProxy, $blogId);
            }
        }

        return $blogs;
    }

    public function testRun($data): void
    {
        if ($data === "") {
            $data = $_POST;
        }
        if (!isset($data['sourceBlogId'], $data['targetBlogId'])) {
            wp_send_json_error('Required parameter missing');
        }
        try {
            $profile = $this->settingsManager->getSingleSettingsProfile((int)$data['sourceBlogId']);
        } catch (SmartlingDbException $e) {
            wp_send_json_error('Unable to get active profile for blogId=' . $data['sourceBlogId']);
        }
        $targetBlogId = (int)$data['targetBlogId'];
        SimpleStorageHelper::set(TestRunHelper::TEST_RUN_BLOG_ID_SETTING_NAME, $targetBlogId);

        try {
            $job = $this->getJob($profile);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }

        $postsAndPages = get_posts([
            'numberposts' => -1,
            'post_type' => ['page', 'post'],
        ]);

        foreach ($postsAndPages as $post) {
            if (!$post instanceof \WP_Post) {
                wp_send_json_error('Unable to get posts');
            }
            $this->contentRelationDiscoveryService->createSubmissions(new UserTranslationRequest(
                $post->ID,
                $post->post_type,
                [$targetBlogId => $this->contentRelationDiscoveryService
                    ->getRelations($post->post_type, $post->ID, [$targetBlogId])->getReferences()],
                [$targetBlogId],
                new JobInformation($job->getJobUid(), true, $job->getJobName(), 'Test run job', '', 'UTC'),
                [],
                'Test run'
            ));
        }

        wp_send_json_success('Test run started');
    }

    private function getJob(ConfigurationProfileEntity $profile): JobEntity
    {
        $response = $this->apiWrapper->listJobs($profile, self::TEST_RUN_JOB_NAME, [
            JobStatus::AWAITING_AUTHORIZATION,
            JobStatus::IN_PROGRESS,
            JobStatus::COMPLETED,
        ]);

        if (!empty($response['items'])) {
            $jobUId = $response['items'][0]['translationJobUid'];
        } else {
            $result = $this->apiWrapper->createJob($profile, [
                    'name' => self::TEST_RUN_JOB_NAME,
                    'description' => 'Test run job',
            ]);
            $jobUId = $result['translationJobUid'];
        }

        if (empty($jobUId)) {
            throw new \RuntimeException('Unable to get job for test run');
        }

        return new JobEntity(self::TEST_RUN_JOB_NAME, $jobUId, $profile->getProjectId());
    }
}
