<?php

namespace Smartling\Tests\IntegrationTests;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\ContentTypes\CustomPostType;
use Smartling\ContentTypes\CustomTaxonomyType;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Jobs\DownloadTranslationJob;
use Smartling\Jobs\UploadJob;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Queue\Queue;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;


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
        if (false === $this->getProfileById(1)) {
            /**
             * @var ConfigurationProfileEntity $profile
             */
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
        ];

        $tablePrefix = getenv('WP_DB_TABLE_PREFIX');

        $template = "'TRUNCATE TABLE `%s%s`;'";

        foreach ($tableList as $tableName) {
            $query = vsprintf($template, [$tablePrefix, $tableName]);
            $this->wpcli_exec('db', 'query', vsprintf('%s', [$query]));
        }
    }

    public function setUp()
    {
        parent::setUp();
        $this->cleanUpTables();
        $this->registerPostTypes();
        $this->ensureProfileExists();
    }


    /**
     * @var ConfigurationProfileEntity
     */
    protected $profile = null;

    /**
     * @param string $envVar
     *
     * @return string
     */
    private function getWPcliEnv($envVar = 'WPCLI')
    {
        return getenv($envVar);
    }

    /**
     * @param string $envVar
     *
     * @return string
     */
    private function getWPInstallDirEnv($envVar = 'WP_INSTALL_DIR')
    {
        return getenv($envVar);
    }

    /**
     * @param \Smartling\Submissions\SubmissionEntity $submission
     *
     * @return bool|mixed
     */
    protected function uploadDownload(SubmissionEntity $submission)
    {
        $this->executeUpload();
        $this->forceSubmissionDownload($submission);

        return $this->getSubmissionById($submission->getId());
    }

    protected function editPost($edit = [])
    {
        wp_update_post($edit);
        self::flush_cache();
    }

    protected function forceSubmissionDownload(SubmissionEntity $submission)
    {
        $queue = $this->get('queue.db');
        /**
         * @var Queue $queue
         */
        $queue->enqueue([$submission->getId()], Queue::QUEUE_NAME_DOWNLOAD_QUEUE);
        $this->executeDownload();
    }

    /**
     * @param string $command
     * @param string $subCommand
     * @param string $params
     */
    protected function wpcli_exec($command, $subCommand, $params)
    {
        shell_exec(
            vsprintf(
                '%s %s %s %s --path=%s',
                [
                    $this->getWPcliEnv(),
                    $command,
                    $subCommand,
                    $params,
                    $this->getWPInstallDirEnv(),
                ]
            )
        );
    }

    /**
     * @return ContainerBuilder
     * @throws \Smartling\Exception\SmartlingConfigException
     */
    protected function getContainer()
    {
        return Bootstrap::getContainer();
    }

    /**
     * @return SettingsManager
     */
    protected function getSettingsManager()
    {
        return $this->get('manager.settings');
    }

    /**
     * @return SubmissionManager
     */
    protected function getSubmissionManager()
    {
        return $this->get('manager.submission');
    }

    /**
     * @return TranslationHelper
     */
    protected function getTranslationHelper()
    {
        return $this->get('translation.helper');
    }

    /**
     * @param $tag
     *
     * @return object
     * @throws \Exception
     */
    protected function get($tag)
    {
        return $this->getContainer()->get($tag);
    }

    protected function param($tag)
    {
        return $this->getContainer()->getParameter($tag);
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return MonologWrapper::getLogger(get_called_class());
    }

    protected function createProfile()
    {
        $profile = new ConfigurationProfileEntity();
        $profile->setProfileName('testProfile');
        $profile->setProjectId(getenv('CRE_PROJECT_ID'));
        $profile->setUserIdentifier(getenv('CRE_USER_IDENTIFIER'));
        $profile->setSecretKey(getenv('CRE_TOKEN_SECRET'));
        $profile->setIsActive(1);

        $originalBlog = new Locale();
        $originalBlog->setBlogId(1);
        $originalBlog->setLabel('');

        $profile->setOriginalBlogId($originalBlog);
        $profile->setAutoAuthorize(1);
        $profile->setRetrievalType('pseudo');
        $profile->setUploadOnUpdate(1);
        $profile->setPublishCompleted(0);
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

    protected function createAttachment($filename = 'canola.jpg')
    {
        return $this->factory()->attachment->create_upload_object(DIR_TESTDATA . '/' . $filename);
    }

    protected function createPost($post_type = 'post', $title = 'title', $content = 'content')
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

    private function runCronTask($task)
    {
        $this->wpcli_exec('cron', 'event', vsprintf('run %s', [$task]));
    }

    protected function executeUpload()
    {
        $this->runCronTask(UploadJob::JOB_HOOK_NAME);
    }

    protected function executeDownload()
    {

        $this->runCronTask(DownloadTranslationJob::JOB_HOOK_NAME);
    }

    /**
     * @param string $contentType
     * @param int    $sourceId
     * @param int    $sourceBlogId
     * @param int    $targetBlogId
     *
     * @return SubmissionEntity
     */
    protected function createSubmission($contentType, $sourceId, $sourceBlogId = 1, $targetBlogId = 2)
    {
        $submission = $this->getTranslationHelper()
            ->prepareSubmission($contentType, $sourceBlogId, $sourceId, $targetBlogId);

        return $submission;
    }

    /**
     * @return ContentHelper
     */
    protected function getContentHelper()
    {
        /**
         * @var ContentHelper $contentHelper
         */
        $contentHelper = $this->get('content.helper');

        return $contentHelper;
    }

    /**
     * @param $id
     *
     * @return bool|ConfigurationProfileEntity
     */
    public function getProfileById($id)
    {
        $result = $this->getSettingsManager()->getEntityById($id);
        if (0 < $this->count($result)) {
            return ArrayHelper::first($result);
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return bool|SubmissionEntity
     */
    public function getSubmissionById($id)
    {
        $result = $this->getSubmissionManager()->getEntityById($id);
        if (0 < $this->count($result)) {
            return ArrayHelper::first($result);
        }

        return false;
    }

    /**
     * @param string $title
     * @param string $body
     * @param string $post_type
     * @param array  $meta
     *
     * @return int
     */
    protected function createPostWithMeta($title, $body, $post_type = 'post', array $meta)
    {
        $template = [
            'post_title'   => $title,
            'post_content' => $body,
            'post_status'  => 'publish',
            'post_type'    => $post_type,
            'meta_input'   => $meta,
        ];

        $postId = $this->factory()->post->create_object($template);

        return $postId;
    }

    protected function createTerm($name, $taxonomy = 'category')
    {
        $categoryResult = wp_insert_term($name, $taxonomy);
        $categoryId = $categoryResult['term_id'];

        return $categoryId;
    }

    protected function addTaxonomyToPost($postId, $termId) {
        global $wpdb;
        $queryTemplate = "REPLACE INTO `%sterm_relationships` VALUES('%s', '%s', 0)";
        $query = vsprintf($queryTemplate, [$wpdb->base_prefix, $postId, $termId]);
        $wpdb->query($query);
    }
}
