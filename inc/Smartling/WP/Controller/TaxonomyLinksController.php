<?php

namespace Smartling\WP\Controller;

use Smartling\ApiWrapperInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressUserHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TaxonomyLinksController extends WPAbstract implements WPHookInterface
{
    public function __construct(
        protected ApiWrapperInterface $api,
        PluginInfo $pluginInfo,
        SettingsManager $settingsManager,
        LocalizationPluginProxyInterface $localizationPluginProxy,
        SiteHelper $siteHelper,
        protected SubmissionManager $submissionManager,
        private WordpressFunctionProxyHelper $wordpressProxy,
        Cache $cache,
    )
    {
        parent::__construct($api, $localizationPluginProxy, $pluginInfo, $settingsManager, $siteHelper, $submissionManager, $cache);
    }

    public function buildViewData()
    {
        return ['blogs' => $this->getBlogs(), 'terms' => $this->getTerms(), 'submissions' => $this->getSubmissions()];
    }

    public function wp_enqueue()
    {
        wp_enqueue_script(
            $this->pluginInfo->getName() . 'settings',
            $this->pluginInfo->getUrl() . 'js/smartling-connector-taxonomy-links.js', ['jquery'],
            $this->pluginInfo->getVersion(),
            false
        );
        wp_enqueue_script(
            $this->pluginInfo->getName() . 'admin',
            $this->pluginInfo->getUrl() . 'js/smartling-connector-admin.js', ['jquery'],
            $this->pluginInfo->getVersion(),
            false
        );
        wp_register_style(
            $this->pluginInfo->getName(),
            $this->pluginInfo->getUrl() . 'css/smartling-connector-admin.css', [],
            $this->pluginInfo->getVersion(), 'all'
        );
        wp_enqueue_style($this->pluginInfo->getName());
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('wp_ajax_smartling_link_taxonomies', [$this, 'linkTaxonomies']);
    }

    public function menu()
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Taxonomy links',
            'Taxonomy links',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_MENU_CAP,
            'smartling_taxonomy_links',
            [$this, 'taxonomyLinksWidget']
        );
    }

    public function taxonomyLinksWidget()
    {
        $this->view($this->buildViewData());
    }

    public function getBlogs()
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

    public function getTerms()
    {
        $sourceBlogId = get_current_blog_id();

        $terms = [$sourceBlogId => $this->getMappedTerms()];

        foreach ($this->siteHelper->listBlogs() as $targetBlogId) {
            if ($sourceBlogId !== $targetBlogId) {
                $terms[$targetBlogId] = $this->getBlogTerms($targetBlogId);
            }
        }

        return $terms;
    }

    public function getSubmissions()
    {
        $submissions = [];
        foreach ($this->submissionManager->find([
            SubmissionEntity::FIELD_CONTENT_TYPE => $this->wordpressProxy->get_taxonomies(),
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => get_current_blog_id(),
        ]) as $submission) {
            $submissions[$submission->getSourceId()][$submission->getTargetBlogId()] = $submission->getTargetId();
        }
        return $submissions;
    }

    /**
     * @param int $blogId
     * @return array
     */
    private function getBlogTerms($blogId)
    {
        $this->siteHelper->switchBlogId($blogId);
        $terms = $this->getMappedTerms();
        $this->siteHelper->restoreBlogId();
        return $terms;
    }

    /**
     * @return array
     */
    private function getMappedTerms()
    {
        $return = [];
        foreach ($this->wordpressProxy->get_terms(['hide_empty' => false]) as $term) {
            $return[$term->taxonomy][] = ['value' => $term->term_id, 'label' => $term->name];
        }
        return $return;
    }

    public function linkTaxonomies($data)
    {
        if ($data === "") {
            $data = $_POST;
        }
        if (!isset($data['sourceBlogId'], $data['sourceId'], $data['taxonomy'])) {
            wp_send_json_error('Required parameter missing');
        }
        $sourceBlogId = (int)$data['sourceBlogId'];
        $sourceId = (int)$data['sourceId'];
        $taxonomy = $data['taxonomy'];

        $submissionsToAdd = [];
        $submissionsToDelete = [];
        $submissionsToUpdate = [];

        foreach ($data['targetId'] as $targetBlogId => $targetId) {
            $targetBlogId = (int)$targetBlogId;
            $targetId = (int)$targetId;
            if ($targetId === 0) {
                $submissionsToDelete = $this->addToDeleteListIfNeeded($submissionsToDelete, $sourceBlogId, $sourceId, $targetBlogId, $taxonomy);
            } else {
                $submissions = $this->submissionManager->find([
                    SubmissionEntity::FIELD_CONTENT_TYPE => $taxonomy,
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                    SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                ]);
                if (is_array($submissions) && count($submissions) > 1) {
                    $this->getLogger()->error(sprintf("More than a single submission exists for $taxonomy: sourceBlogId=%d, sourceBlog=%d, targetBlog=%d", $sourceBlogId, $sourceId, $targetBlogId));
                }
                $existingSubmission = ArrayHelper::first($submissions);
                if ($existingSubmission !== false) {
                    if ($existingSubmission->getTargetId() !== $targetId) {
                        $existingSubmission->setTargetId($targetId);
                        $existingSubmission->setSubmissionDate(date(SubmissionEntity::DATETIME_FORMAT));
                        $submissionsToUpdate[] = $existingSubmission;
                    }
                } else {
                    $submissionsToAdd[] = $this->submissionManager->createSubmission([
                        SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                        SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                        SubmissionEntity::FIELD_CONTENT_TYPE => $taxonomy,
                        SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
                        SubmissionEntity::FIELD_STATUS => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
                        SubmissionEntity::FIELD_TARGET_ID => $targetId,
                        SubmissionEntity::FIELD_SUBMISSION_DATE => date(SubmissionEntity::DATETIME_FORMAT),
                        SubmissionEntity::FIELD_SUBMITTER => WordpressUserHelper::getUserLogin(),
                    ]);
                }
            }
        }
        $submissions = array_merge($submissionsToAdd, $submissionsToUpdate);
        if (count(array_merge($submissions, $submissionsToDelete)) === 0) {
            wp_send_json_error('No changes');
        }
        $this->submissionManager->storeSubmissions($submissions);
        foreach ($submissionsToDelete as $submission) {
            $this->submissionManager->delete($submission);
        }
        wp_send_json(['success' => true, 'submissions' => $this->getSubmissions()]);
    }

    /**
     * @param SubmissionEntity[] $list
     * @param int $sourceBlogId
     * @param int $sourceId
     * @param int $targetBlogId
     * @param string $taxonomy
     * @return SubmissionEntity[]
     */
    private function addToDeleteListIfNeeded($list, $sourceBlogId, $sourceId, $targetBlogId, $taxonomy)
    {
        $existingSubmission = ArrayHelper::first($this->submissionManager->find([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            SubmissionEntity::FIELD_CONTENT_TYPE => $taxonomy,
        ]));
        if ($existingSubmission !== false && $existingSubmission->getId() !== null) {
            $list[] = $existingSubmission;
        }
        return $list;
    }
}
