<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\Cache;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Jobs\JobEntity;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Jobs\JobStatus;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TestRunController extends WPAbstract implements WPHookInterface
{
    private const TEST_RUN_JOB_NAME = 'Test Run Job';
    public const TEST_RUN_BLOG_ID_SETTING_NAME = 'TestRunBlogId';

    protected LocalizationPluginProxyInterface $localizationPluginProxy;
    protected SiteHelper $siteHelper;
    private ContentRelationsDiscoveryService $contentRelationDiscoveryService;
    private ApiWrapperInterface $apiWrapper;
    private SettingsManager $settingsManager;

    public function __construct(PluginInfo $pluginInfo, LocalizationPluginProxyInterface $localizationPluginProxy, SiteHelper $siteHelper, SubmissionManager $submissionManager, EntityHelper $entityHelper, Cache $cache, ContentRelationsDiscoveryService $contentRelationDiscoveryService, ApiWrapperInterface $apiWrapper, SettingsManager $settingsManager)
    {
        parent::__construct($localizationPluginProxy, $pluginInfo, $entityHelper, $submissionManager, $cache);
        $this->apiWrapper = $apiWrapper;
        $this->contentRelationDiscoveryService = $contentRelationDiscoveryService;
        $this->localizationPluginProxy = $localizationPluginProxy;
        $this->settingsManager = $settingsManager;
        $this->siteHelper = $siteHelper;
    }

    public function buildViewData(): array
    {
        return ['blogs' => $this->getBlogs()];
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
            'smartling_test_run',
            [$this, 'widget']
        );
    }

    public function widget(): void
    {
        $this->view($this->buildViewData());
    }

    public function getBlogs(): array
    {
        $blogs = [];
        $currentBlogId = $this->siteHelper->getCurrentBlogId();
        foreach ($this->siteHelper->listBlogs($this->siteHelper->getCurrentSiteId()) as $blogId) {
            if ($currentBlogId !== $blogId) {
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
            wp_send_json_error(['message' => 'Unable to get active profile for blogId=' . $data['sourceBlogId']]);
        }
        if ($profile->getRetrievalType() !== ConfigurationProfileEntity::RETRIEVAL_TYPE_PSEUDO) {
            wp_send_json_error(['message' => 'Active profile\'s retrieval type must be ' . ConfigurationProfileEntity::getRetrievalTypes()[ConfigurationProfileEntity::RETRIEVAL_TYPE_PSEUDO]]);
        }
        $targetBlogId = (int)$data['targetBlogId'];
        SimpleStorageHelper::set(self::TEST_RUN_BLOG_ID_SETTING_NAME, $targetBlogId);

        try {
            $job = $this->getJob($profile);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        $postsAndPages = get_posts([
            'numberposts' => -1,
            'post_type' => ['page', 'post'],
        ]);

        foreach ($postsAndPages as $post) {
            if (!$post instanceof \WP_Post) {
                wp_send_json_error(['message' => 'Unable to get posts']);
            }
            $relations = $this->contentRelationDiscoveryService->actionHandler([
                'content-type' => $post->post_type,
                'id' => $post->ID,
                'targetBlogIds' => $targetBlogId,
            ]);

            $this->contentRelationDiscoveryService->createSubmissions([
                'source' => [
                    'contentType' => $post->post_type,
                    'id' => [$post->ID]
                ],
                'job' =>
                    [
                        'id' => $job->getJobUid(),
                        'name' => $job->getJobName(),
                        'description' => '',
                        'dueDate' => '',
                        'timeZone' => 'UTC',
                        'authorize' => 'true',
                    ],
                'targetBlogIds' => $targetBlogId,
                'relations' => $relations,
            ]);
        }

        wp_send_json_success(['posts' => count($postsAndPages)]);
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
