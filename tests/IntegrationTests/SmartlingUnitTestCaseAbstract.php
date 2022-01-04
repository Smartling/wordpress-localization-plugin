<?php

namespace Smartling\Tests\IntegrationTests;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\ContentTypes\CustomPostType;
use Smartling\ContentTypes\CustomTaxonomyType;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\SubmissionJobEntity;
use Smartling\Jobs\UploadJob;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Queue\Queue;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tuner\MediaAttachmentRulesManager;
use Smartling\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class SmartlingUnitTestCaseAbstract extends WP_UnitTestCase
{
    protected function registerPostTypes()
    {
        if (!function_exists('create_initial_post_types')) {
            require_once ABSPATH . '/wp-includes/post.php';
            create_initial_post_types();
        }

        if (!function_exists('create_initial_taxonomies')) {
            require_once ABSPATH . '/wp-includes/taxonomy.php';
            create_initial_taxonomies();
        }

        $taxonomiesToRegister = ['post_tag', 'category'];

        foreach ($taxonomiesToRegister as $item) {
            CustomTaxonomyType::registerCustomType($this->getContainer(), [
                'taxonomy' => [
                    'identifier' => $item,
                    'widget'     => [
                        'visible' => true,
                    ],
                    'visibility' => [
                        'submissionBoard' => true,
                        'bulkSubmit'      => true,
                    ],
                ],
            ]);
        }

        $postTypesToRegister = ['post', 'page', 'attachment'];

        foreach ($postTypesToRegister as $item) {
            CustomPostType::registerCustomType($this->getContainer(), [
                "type" =>
                    [
                        'identifier' => $item,
                        'widget'     => [
                            'visible' => false,
                        ],
                        'visibility' => [
                            'submissionBoard' => true,
                            'bulkSubmit'      => true,
                        ],
                    ],
            ]);
        }
    }

    protected function ensureProfileExists()
    {
        if (null === $this->getProfileById(1)) {
            $profile = $this->createProfile();

            $this->getSettingsManager()->storeEntity($profile);
        }
    }

    protected function cleanUpTables()
    {
        $tableList = [
            'postmeta',
            'posts',
            'termmeta',
            'terms',
            'term_relationships',
            'term_taxonomy',
            'smartling_configuration_profiles',
            'smartling_queue',
            'smartling_submissions',
            JobEntity::getTableName(),
            SubmissionJobEntity::getTableName(),
        ];

        $tablePrefix = getenv('WP_DB_TABLE_PREFIX');

        $template = "'TRUNCATE TABLE `%s%s`;'";

        foreach ($tableList as $tableName) {
            $query = vsprintf($template, [$tablePrefix, $tableName]);
            self::wpCliExec('db', 'query', vsprintf('%s', [$query]));
        }
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->cleanUpTables();
        $this->registerPostTypes();
        $this->ensureProfileExists();
        GlobalSettingsManager::setHandleRelationsManually(0);
    }

    protected ConfigurationProfileEntity $profile;

    private static function getWPcliEnv(): string
    {
        return getenv('WPCLI');
    }

    private static function getWPInstallDirEnv(): string
    {
        return getenv('WP_INSTALL_DIR');
    }

    public function getContentRelationsDiscoveryService(): ContentRelationsDiscoveryService
    {
        return $this->get('service.relations-discovery');
    }

    public function withBlockRules(MediaAttachmentRulesManager $manager, array $rules, callable $function)
    {
        try {
            foreach ($rules as $offset => $value) {
                $manager->offsetSet($offset, $value);
            }
            $manager->saveData();
            return $function();
        } finally {
            foreach (array_keys($rules) as $offset) {
                $manager->offsetUnset($offset);
            }
            $manager->saveData();
        }
    }

    protected function uploadDownload(SubmissionEntity $submission): ?SubmissionEntity
    {
        $this->executeUpload();
        $this->forceSubmissionDownload($submission);

        return $this->getSubmissionById($submission->getId());
    }

    protected function editPost(array $edit = []): void
    {
        wp_update_post($edit);
        self::flush_cache();
    }

    protected function forceSubmissionDownload(SubmissionEntity $submission): void
    {
        $queue = $this->get('queue.db');
        /**
         * @var Queue $queue
         */
        $queue->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
        $this->executeDownload();
    }

    protected static function wpCliExec(string $command, string $subCommand, string $parameters): void
    {
        shell_exec(sprintf('%s %s %s %s --path=%s', self::getWPcliEnv(), $command, $subCommand, $parameters, self::getWPInstallDirEnv()));
    }

    protected function getContainer(): ContainerBuilder
    {
        return Bootstrap::getContainer();
    }

    protected function getSettingsManager(): SettingsManager
    {
        return $this->get('manager.settings');
    }

    protected function getSubmissionManager(): SubmissionManager
    {
        return $this->get('manager.submission');
    }

    protected function getTranslationHelper(): TranslationHelper
    {
        return $this->get('translation.helper');
    }

    protected function getSiteHelper(): SiteHelper
    {
        return $this->get('site.helper');
    }

    protected function getRulesManager(): MediaAttachmentRulesManager
    {
        return $this->get('media.attachment.rules.manager');
    }

    protected function getGutenbergBlockHelper(): GutenbergBlockHelper
    {
        return $this->get('helper.gutenberg');
    }

    /**
     * @return mixed
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function get(string $tag)
    {
        return $this->getContainer()->get($tag);
    }

    /**
     * @return mixed
     */
    protected function param(string $tag)
    {
        return $this->getContainer()->getParameter($tag);
    }

    protected function getLogger(): LoggerInterface
    {
        return MonologWrapper::getLogger(get_called_class());
    }

    protected function createProfile(): ConfigurationProfileEntity
    {
        $profile = new ConfigurationProfileEntity();
        $profile->setProfileName('testProfile');
        $profile->setProjectId(getenv('CRE_PROJECT_ID'));
        $profile->setUserIdentifier(getenv('CRE_USER_IDENTIFIER'));
        $profile->setSecretKey(getenv('CRE_TOKEN_SECRET'));
        $profile->setIsActive(1);

        $locale = new Locale();
        $locale->setBlogId(1);
        $locale->setLabel('');

        $profile->setLocale($locale);
        $profile->setAutoAuthorize(1);
        $profile->setRetrievalType('pseudo');
        $profile->setUploadOnUpdate(1);
        $profile->setChangeAssetStatusOnCompletedTranslation(0);
        $profile->setDownloadOnChange(0);
        $profile->setCleanMetadataOnDownload(0);
        $profile->setAlwaysSyncImagesOnUpload(0);

        $sites = getenv('SITES');
        $sitesA = explode(',', $sites);

        $tLocales = [];

        foreach ($sitesA as $i => $siteDefinition) {
            $definition = explode(':', $siteDefinition);

            $arr = [
                'smartlingLocale' => $definition[1],
                'enabled'         => true,
                'blogId'          => (2 + $i),
            ];

            $tLocales[] = TargetLocale::fromArray($arr);
        }

        $profile->setTargetLocales($tLocales);

        $filterParams = $this->param('field.processor.default');

        $profile->setFilterSkip(implode(PHP_EOL, $filterParams['ignore']));
        $profile->setFilterCopyByFieldName(implode(PHP_EOL, $filterParams['copy']['name']));
        $profile->setFilterCopyByFieldValueRegex(implode(PHP_EOL, $filterParams['copy']['regexp']));
        $profile->setFilterFlagSeo(implode(PHP_EOL, $filterParams['key']['seo']));

        return $profile;
    }

    /**
     * @return int|\WP_Error
     */
    protected function createAttachment(string $filename = 'canola.jpg')
    {
        return $this->factory()->attachment->create_upload_object(DIR_TESTDATA . '/' . $filename);
    }

    /**
     * @return mixed
     */
    protected function createPost(string $post_type = 'post', string $title = 'title', string $content = 'content')
    {
        return $this->factory()->post->create_object(
            [
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $content,
                'post_excerpt' => '',
                'post_type'    => $post_type,
            ]);
    }

    private function runCronTask(string $task): void
    {
        self::wpCliExec('cron', 'event', vsprintf('run %s', [$task]));
    }

    protected function executeUpload(): void
    {
        /**
         * Should be executed twice.
         * 1-st pass generates batch_uid;
         * 2-nd pass executes upload job
         */
        $this->runCronTask(UploadJob::JOB_HOOK_NAME);
        $this->runCronTask(UploadJob::JOB_HOOK_NAME);
    }

    protected function executeDownload(): void
    {
        $this->runCronTask(DownloadTranslationJob::JOB_HOOK_NAME);
    }

    protected function createSubmission(string $contentType, int $sourceId, int $sourceBlogId = 1, int $targetBlogId = 2): SubmissionEntity
    {
        return $this->getTranslationHelper()
            ->prepareSubmission($contentType, $sourceBlogId, $sourceId, $targetBlogId);
    }

    protected function getContentHelper(): ContentHelper
    {
        return $this->get('content.helper');
    }

    public function getProfileById(int $id): ?ConfigurationProfileEntity
    {
        $result = $this->getSettingsManager()->getEntityById($id);
        if (0 < count($result)) {
            return ArrayHelper::first($result);
        }

        return null;
    }

    public function getSubmissionById(int $id): ?SubmissionEntity
    {
        $result = $this->getSubmissionManager()->getEntityById($id);
        if (0 < count($result)) {
            return ArrayHelper::first($result);
        }

        return null;
    }

    protected function createPostWithMeta(string $title, string $body, string $post_type, array $meta): int
    {
        $template = [
            'post_title'   => $title,
            'post_content' => $body,
            'post_status'  => 'publish',
            'post_type'    => $post_type,
            'meta_input'   => $meta,
        ];

        return $this->factory()->post->create_object($template);
    }

    protected function createTerm(string $name, string $taxonomy = 'category'): int
    {
        $categoryResult = wp_insert_term($name, $taxonomy);
        return $categoryResult['term_id'];
    }

    protected function addTaxonomyToPost(int $postId, int $termId): void {
        global $wpdb;
        $queryTemplate = "REPLACE INTO `%sterm_relationships` VALUES('%s', '%s', 0)";
        $query = vsprintf($queryTemplate, [$wpdb->base_prefix, $postId, $termId]);
        $wpdb->query($query);
    }

    protected function getTargetPost(SiteHelper $siteHelper, SubmissionEntity $submission): \WP_Post
    {
        $siteHelper->switchBlogId($submission->getTargetBlogId());
        wp_cache_delete($submission->getTargetId(), 'posts');
        $post = get_post($submission->getTargetId());
        $siteHelper->restoreBlogId();

        return $post;
    }
}
