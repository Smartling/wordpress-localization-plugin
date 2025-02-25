<?php

namespace Smartling\WP\Controller;

use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Models\DuplicateSubmissionDetails;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\Table\DuplicateSubmissions;
use Smartling\WP\WPHookInterface;

class DuplicateSubmissionsCleaner extends ControllerAbstract implements WPHookInterface {
    public const SLUG = 'smartling_duplicate_submission_cleaner';

    private const CACHE_KEY = 'submissions-has-duplicate';
    private const NONCE_ACTION = "duplicate-submission-cleaner";

    public function __construct(
        private Cache $cache,
        private ContentHelper $contentHelper,
        private SiteHelper $siteHelper,
        private SubmissionManager $submissionManager,
    ) {
    }

    public function register(): void
    {
        $duplicates = $this->getDuplicatesAndCache();
        if (count($duplicates) > 0) {
            add_action('admin_menu', [$this, 'menu']);
            add_action('network_admin_menu', [$this, 'menu']);
            add_action('admin_post_' . self::SLUG, [$this, 'pageHandler']);
        }
    }

    public function menu(): void
    {
        add_submenu_page(
            'smartling-submissions-page',
            'Duplicate submissions cleaning',
            'Duplicate submissions cleaning',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            self::SLUG,
            [$this, 'pageHandler'],
        );
    }

    private function processAction(): void
    {
        check_admin_referer(self::NONCE_ACTION);
        $action = $_REQUEST['action'];

        if ('delete' !== strtolower($action)) {
            return;
        }

        if (array_key_exists('id', $_REQUEST)) {
            $submission = $this->submissionManager->getEntityById((int)$_REQUEST['id']);
            if ($submission !== null) {
                foreach ($this->getDuplicatesAndCache(false) as $details) {
                    if ($details->contentType === $submission->getContentType() &&
                        $details->sourceBlogId === $submission->getSourceBlogId() &&
                        $details->sourceId === $submission->getSourceId() &&
                        $details->targetBlogId === $submission->getTargetBlogId()
                    ) {
                        $this->submissionManager->delete($submission);
                        break;
                    }
                }
            }
        }
    }

    public function pageHandler(): void
    {
        $getFromCacheFirst = true;
        if (array_key_exists('action', $_REQUEST)) {
            $this->processAction();
            $getFromCacheFirst = false;
        }
        $nonce = wp_create_nonce(self::NONCE_ACTION);

        $duplicateSets = [];
        foreach($this->getDuplicatesAndCache($getFromCacheFirst) as $set) {
            $duplicateSets[] = new DuplicateSubmissions($this->contentHelper, $this->siteHelper, $this->submissionManager, $set, $nonce);
        }
        $this->setViewData(['duplicates' => $duplicateSets]);
        $this->renderScript();
    }

    /**
     * @return DuplicateSubmissionDetails[]
     */
    private function getDuplicatesAndCache($getFromCacheFirst = true): array
    {
        $duplicates = false;
        if ($getFromCacheFirst) {
            $duplicates = $this->cache->get(self::CACHE_KEY);
        }
        if (!is_array($duplicates)) {
            $duplicates = $this->submissionManager->getDuplicateSubmissionDetails();
            $this->cache->set(self::CACHE_KEY, $duplicates);
        }

        return $duplicates;
    }
}
