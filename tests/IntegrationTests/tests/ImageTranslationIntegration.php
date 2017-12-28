<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Bootstrap;
use Smartling\ContentTypes\CustomPostType;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ImageTranslationIntegration extends \WP_UnitTestCase
{

    private $imageId = 0;

    private function wpcli_exec($command, $subCommand, $params)
    {
        $wpCli = getenv('WPCLI');
        $wpPath = getenv('WP_INSTALL_DIR');

        $template = '%s %s %s %s --path=%s';

        $execString = vsprintf($template, [$wpCli, $command, $subCommand, $params, $wpPath]);
        shell_exec($execString);
    }

    public function setUp()
    {
        if (!function_exists('create_initial_post_types')) {
            require_once ABSPATH . '/wp-includes/post.php';
            create_initial_post_types();
        }

    }

    public function tearDown(){}

    public static function tearDownAfterClass(){}

    public function getLogger()
    {
        return Bootstrap::getContainer()->get('logger');
    }

    private function createProfile()
    {
        $profile = new ConfigurationProfileEntity($this->getLogger());
        $profile->setProfileName('testProfile');
        // todo: read from env
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

        $locales = [
            [
                'smartlingLocale' => 'es',
                'enabled'         => true,
                'blogId'          => 2,
            ],
            [
                'smartlingLocale' => 'fr-FR',
                'enabled'         => true,
                'blogId'          => 3,
            ],
            [
                'smartlingLocale' => 'ru-RU',
                'enabled'         => true,
                'blogId'          => 4,
            ],
            [
                'smartlingLocale' => 'uk-UA',
                'enabled'         => true,
                'blogId'          => 5,
            ],
        ];

        $tLocales = [];

        foreach ($locales as $locale) {
            $tLocales[] = TargetLocale::fromArray($locale);
        }

        $profile->setTargetLocales($tLocales);
        $profile->setFilterSkip('_edit_lock\r\n_edit_last\r\n_yoast_wpseo_canonical\r\n_yoast_wpseo_redirect\r\npost_date\r\npost_date_gmt\r\npost_modified\r\npost_modified_gmt\r\nguid\r\ncomment_count\r\npost_name\r\npost_status\r\nhash\r\nID\r\nid\r\nterm_id\r\nslug\r\nterm_group\r\nterm_taxonomy_id\r\nsmartlingId\r\nattachment_id\r\ntestimonial_id\r\ntestimonials\r\n_wp_attachment_metadata.*\r\n_kraken.*\r\n_kraked.*');
        $profile->setFilterCopyByFieldName('_yoast_wpseo_meta-robots-noindex\r\n_yoast_wpseo_meta-robots-nofollow\r\n_yoast_wpseo_meta-robots-adv\r\n_yoast_wpseo_opengraph-image\r\npost_parent\r\nparent\r\ncomment_status\r\nping_status\r\npost_password\r\nto_ping\r\npinged\r\npost_content_filtered\r\npost_type\r\npost_mime_type\r\npost_author\r\ntaxonomy\r\nbackground\r\neffective_date\r\nicon\r\nmenu_order\r\n_wp_page_template\r\n_marketo_sidebar\r\n_post_restored_from\r\n_wp_attached_file\r\nfile\r\nalign\r\nclass\r\nmime-type\r\nbar\r\nwidgetType\r\ncount\r\ndropdown\r\nhierarchical\r\nsortby\r\nexclude\r\nnumber\r\nfilter\r\ntaxonomy\r\nshow_date\r\nurl\r\nitems\r\nshow_summary\r\nshow_author\r\nshow_date');
        $profile->setFilterCopyByFieldValueRegex('^\\d+([,\\.]\\d+)?$\r\n^(y|yes|n|no|on|off|default|in|out|html|cta\\d+|cta|any|null|text|surveys|choose|button)$\r\n^(http:|https:|field_)\r\n^(callout|card-list|card-icon-list|cta|cta-hero|cta-sidebar|image-text-list|list-icon|list|nav|template-list|embeds|html|basic|select|gold|platinum)$\r\n^(taxonomy|category|\\s+)$\r\n^(true|false|enabled|disabled|background-image)$');
        $profile->setFilterFlagSeo('_yoast_wpseo_title\r\n_yoast_wpseo_bctitle\r\n_yoast_wpseo_metadesc\r\n_yoast_wpseo_metakeywords\r\n_yoast_wpseo_focuskw\r\n_yoast_wpseo_opengraph-description\r\n_yoast_wpseo_google-plus-description');


        return $profile;

    }

    public function testCreateProfile()
    {
        $profile = $this->createProfile();
        /**
         * @var SettingsManager $manager
         */
        $manager = Bootstrap::getContainer()->get('manager.settings');

        $profile = $manager->storeEntity($profile);

        /**
         * Check that profile is created
         */
        $this->assertTrue(1 === $profile->getId());
    }

    public function testCreateSubmission()
    {

        $this->createAttachment();

        /**
         * @var TranslationHelper $translationHelper
         */
        $translationHelper = Bootstrap::getContainer()->get('translation.helper');

        $this->commit_transaction();


        CustomPostType::registerCustomType(Bootstrap::getContainer(), [
            "type" =>
                [
                    'identifier' => 'attachment',
                    'widget'     => [
                        'visible' => false,
                    ],
                    'visibility' => [
                        'submissionBoard' => true,
                        'bulkSubmit'      => true,
                    ],
                ]
        ]);


        /**
         * @var SubmissionEntity $submission
         */
        $submission = $translationHelper->prepareSubmission('attachment', 1, $this->imageId, 2);

        /**
         * Check submission status
         */
        $this->assertTrue(1 === $submission->getId());
        $this->assertTrue('New' === $submission->getStatus());

    }

    private function createAttachment()
    {
        $id = $this->factory()->attachment->create_upload_object(DIR_TESTDATA . '/canola.jpg');
        $this->imageId = $id;

        return $id;

    }

    public function testImageTranslation()
    {
        $this->wpcli_exec('cron', 'event', 'run smartling-upload-task');

        /**
         * @var SubmissionManager $submissionManager
         */
        $submissionManager = Bootstrap::getContainer()->get('manager.submission');

        $result = $submissionManager->getEntityById(1);
        $submission = ArrayHelper::first($result);
        $this->assertTrue('In Progress' === $submission->getStatus());

        $attachment = (array)$this->factory()->attachment->get_object_by_id(3);

        $guid = $attachment['guid'];

        $filename = str_replace('http://' . getenv('WP_INSTALLATION_DOMAIN'), '' , $guid);
        $this->assertTrue(file_exists($filename));

        $targetFileName = str_replace('uploads', 'uploads/sites/2', $filename);
        $this->assertTrue(file_exists($targetFileName));

        $sourcehash = md5(file_get_contents($filename));
        $targethash = md5(file_get_contents($targetFileName));
        $this->assertTrue($sourcehash === $targethash);

    }
}