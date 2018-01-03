<?php

namespace Smartling\Tests\IntegrationTests;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Helpers\TranslationHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class SmartlingUnitTestCaseAbstract extends \WP_UnitTestCase
{
    public function setUp()
    {
        if (!function_exists('create_initial_post_types')) {
            require_once ABSPATH . '/wp-includes/post.php';
            create_initial_post_types();
        }
    }

    public function tearDown()
    {
    }

    public static function tearDownAfterClass()
    {
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

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->get('logger');
    }

    protected function createProfile()
    {
        $profile = new ConfigurationProfileEntity($this->getLogger());
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
        $profile->setFilterSkip('_edit_lock\r\n_edit_last\r\n_yoast_wpseo_canonical\r\n_yoast_wpseo_redirect\r\npost_date\r\npost_date_gmt\r\npost_modified\r\npost_modified_gmt\r\nguid\r\ncomment_count\r\npost_name\r\npost_status\r\nhash\r\nID\r\nid\r\nterm_id\r\nslug\r\nterm_group\r\nterm_taxonomy_id\r\nsmartlingId\r\nattachment_id\r\ntestimonial_id\r\ntestimonials\r\n_wp_attachment_metadata.*\r\n_kraken.*\r\n_kraked.*');
        $profile->setFilterCopyByFieldName('_yoast_wpseo_meta-robots-noindex\r\n_yoast_wpseo_meta-robots-nofollow\r\n_yoast_wpseo_meta-robots-adv\r\n_yoast_wpseo_opengraph-image\r\npost_parent\r\nparent\r\ncomment_status\r\nping_status\r\npost_password\r\nto_ping\r\npinged\r\npost_content_filtered\r\npost_type\r\npost_mime_type\r\npost_author\r\ntaxonomy\r\nbackground\r\neffective_date\r\nicon\r\nmenu_order\r\n_wp_page_template\r\n_marketo_sidebar\r\n_post_restored_from\r\n_wp_attached_file\r\nfile\r\nalign\r\nclass\r\nmime-type\r\nbar\r\nwidgetType\r\ncount\r\ndropdown\r\nhierarchical\r\nsortby\r\nexclude\r\nnumber\r\nfilter\r\ntaxonomy\r\nshow_date\r\nurl\r\nitems\r\nshow_summary\r\nshow_author\r\nshow_date');
        $profile->setFilterCopyByFieldValueRegex('^\\d+([,\\.]\\d+)?$\r\n^(y|yes|n|no|on|off|default|in|out|html|cta\\d+|cta|any|null|text|surveys|choose|button)$\r\n^(http:|https:|field_)\r\n^(callout|card-list|card-icon-list|cta|cta-hero|cta-sidebar|image-text-list|list-icon|list|nav|template-list|embeds|html|basic|select|gold|platinum)$\r\n^(taxonomy|category|\\s+)$\r\n^(true|false|enabled|disabled|background-image)$');
        $profile->setFilterFlagSeo('_yoast_wpseo_title\r\n_yoast_wpseo_bctitle\r\n_yoast_wpseo_metadesc\r\n_yoast_wpseo_metakeywords\r\n_yoast_wpseo_focuskw\r\n_yoast_wpseo_opengraph-description\r\n_yoast_wpseo_google-plus-description');

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

    protected function executeUpload()
    {
        $this->wpcli_exec('cron', 'event', 'run smartling-upload-task');
    }
}