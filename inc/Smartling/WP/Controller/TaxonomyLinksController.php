<?php

namespace Smartling\WP\Controller;

use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPAbstractLight;
use Smartling\WP\WPHookInterface;

class TaxonomyLinksController extends WPAbstractLight implements WPHookInterface
{
    protected $localizationPluginProxy;
    private $siteHelper;
    private $submissionManager;
    private $wordpressProxy;

    public function __construct(PluginInfo $pluginInfo, LocalizationPluginProxyInterface $localizationPluginProxy, SiteHelper $siteHelper, SubmissionManager $submissionManager, WordpressFunctionProxyHelper $wordpressProxy)
    {
        parent::__construct($pluginInfo);
        $this->localizationPluginProxy = $localizationPluginProxy;
        $this->siteHelper = $siteHelper;
        $this->submissionManager = $submissionManager;
        $this->wordpressProxy = $wordpressProxy;
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

    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('wp_ajax_smartling_get_terms', [$this, 'getTerms']);
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
        $this->view([]);
    }

    public function getTerms($data = null)
    {
        if ($data === null) {
            $data = $_POST;
        }
        if (!isset($data['sourceBlogId'], $data['taxonomy'], $data['targetBlogId'])) {
            return;
        }

        $sourceBlogId = (int)$data['sourceBlogId'];
        $targetBlogId = (int)$data['targetBlogId'];
        $taxonomy = $data['taxonomy'];

        $submissions = $this->submissionManager->find([
            SubmissionEntity::FIELD_CONTENT_TYPE => $taxonomy,
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
        ]);
        $source = $this->getMappedDiff($submissions, $this->wordpressProxy->get_terms(['taxonomy' => $taxonomy]), true);
        $this->siteHelper->switchBlogId($targetBlogId);
        $targetTerms = $this->wordpressProxy->get_terms(['taxonomy' => $taxonomy]); // Can't be inlined, must be run while blog switched
        $this->siteHelper->restoreBlogId();
        $target = $this->getMappedDiff($submissions, $targetTerms, false);

        $this->wordpressProxy->wp_send_json(['source' => $source, 'target' => $target]);
    }

    /**
     * @param SubmissionEntity[] $submissions
     * @param \WP_Term[] $terms
     * @param bool$source
     * @return array
     */
    private function getMappedDiff(array $submissions, array $terms, $source)
    {
        $submissionIds = array_map(static function (SubmissionEntity $submission) use ($source) {
            $field = $submission->getSourceId();
            if (!$source) {
                $field = $submission->getTargetId();
            }
            return (int)$field;
        }, $submissions);
        $termIds = array_map(static function (\WP_Term $term) {
            return $term->term_id;
        }, $terms);
        $diff = array_diff($termIds, $submissionIds);
        $filtered = array_filter($terms, static function (\WP_Term $term) use ($diff) {
            return in_array($term->term_id, $diff, true);
        });
        return array_values(array_map(static function (\WP_Term $term) {
            return ['label' => $term->name, 'value' => $term->term_id];
        }, $filtered));
    }

    public function linkTaxonomies() {
        if (!isset($_POST['sourceBlogId'], $_POST['sourceId'], $_POST['taxonomy'], $_POST['targetBlogId'], $_POST['targetId'])) {
            throw new \RuntimeException('Required parameter missing');
        }
        $sourceBlogId = (int)$_POST['sourceBlogId'];
        $sourceId = (int)$_POST['sourceId'];
        $targetBlogId = (int)$_POST['targetBlogId'];
        $targetId = (int)$_POST['targetId'];
        $taxonomy = $_POST['taxonomy'];
        $submission = $this->submissionManager->createSubmission([
            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
            SubmissionEntity::FIELD_CONTENT_TYPE => $taxonomy,
            SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
            SubmissionEntity::FIELD_STATUS => SubmissionEntity::SUBMISSION_STATUS_COMPLETED,
            SubmissionEntity::FIELD_TARGET_ID => $targetId,
            SubmissionEntity::FIELD_SUBMISSION_DATE => date(SubmissionEntity::DATETIME_FORMAT),
        ]);
        $this->submissionManager->storeEntity($submission);
    }
}
