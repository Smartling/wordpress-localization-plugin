<?php

namespace Smartling\WP\Controller;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressUserHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class TaxonomyLinksController extends WPAbstract implements WPHookInterface
{
    protected $localizationPluginProxy;
    protected $siteHelper;
    private $submissionManager;
    private $wordpressProxy;

    public function __construct(PluginInfo $pluginInfo, LocalizationPluginProxyInterface $localizationPluginProxy, SiteHelper $siteHelper, SubmissionManager $submissionManager, WordpressFunctionProxyHelper $wordpressProxy, EntityHelper $entityHelper, Cache $cache)
    {
        parent::__construct($localizationPluginProxy, $pluginInfo, $entityHelper, $submissionManager, $cache);
        $this->localizationPluginProxy = $localizationPluginProxy;
        $this->siteHelper = $siteHelper;
        $this->submissionManager = $submissionManager;
        $this->wordpressProxy = $wordpressProxy;
        $this->setViewData(['terms' => $this->getTerms(), 'submissions' => $this->getSubmissions()]);
    }

    public function wp_enqueue()
    {
        wp_enqueue_script(
            $this->getPluginInfo()->getName() . 'settings',
            $this->getPluginInfo()->getUrl() . 'js/smartling-connector-taxonomy-links.js', ['jquery'],
            $this->getPluginInfo()->getVersion(),
            false
        );
        wp_enqueue_script(
            $this->getPluginInfo()->getName() . 'admin',
            $this->getPluginInfo()->getUrl() . 'js/smartling-connector-admin.js', ['jquery'],
            $this->getPluginInfo()->getVersion(),
            false
        );
        wp_register_style(
            $this->getPluginInfo()->getName(),
            $this->getPluginInfo()->getUrl() . 'css/smartling-connector-admin.css', [],
            $this->getPluginInfo()->getVersion(), 'all'
        );
        wp_enqueue_style($this->getPluginInfo()->getName());
    }

    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
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
        $this->view($this->getViewData());
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
                $existingSubmission = ArrayHelper::first($this->submissionManager->find([
                    SubmissionEntity::FIELD_CONTENT_TYPE => $taxonomy,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                    SubmissionEntity::FIELD_TARGET_ID => $targetId,
                ]));
                if ($existingSubmission !== false) {
                    if ($existingSubmission->getSourceId() !== $sourceId || $existingSubmission->getSourceBlogId() !== $sourceBlogId) {
                        wp_send_json_error("Duplicate submission for blog {$this->siteHelper->getBlogLabelById($this->localizationPluginProxy, $targetBlogId)}: referenced by {$this->siteHelper->getBlogLabelById($this->localizationPluginProxy,$existingSubmission->getSourceBlogId())} term id {$existingSubmission->getSourceId()}");
                    }
                    if ($existingSubmission->getTargetId() !== $targetId) {
                        $existingSubmission->setTargetId($targetId);
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
