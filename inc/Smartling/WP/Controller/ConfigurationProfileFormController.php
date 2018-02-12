<?php

namespace Smartling\WP\Controller;

use Smartling\Exception\BlogNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\TargetLocale;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class ConfigurationProfileFormController extends WPAbstract implements WPHookInterface
{

    public function wp_enqueue()
    {
        $resPath = $this->getPluginInfo()->getUrl();
        $jsPath = $resPath . 'js/';
        $ver = $this->getPluginInfo()->getVersion();
        wp_enqueue_script('jquery');
        $jsFiles = [
            $jsPath . 'jquery-validate-min.js',
            $jsPath . 'configuration-profile-form.js',
        ];
        foreach ($jsFiles as $jFile) {
            wp_enqueue_script($jFile, $jFile, ['jquery'], $ver, false);
        }
    }

    public function register()
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_configuration_profile_save', [$this, 'save']);
    }

    public function menu()
    {
        add_submenu_page(
            'smartling_configuration_profile_list',
            'Profile setup',
            'Configuration Profile Setup',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_PROFILE_CAP,
            'smartling_configuration_profile_setup',
            [
                $this,
                'edit',
            ]
        );
    }


    public function edit()
    {
        $this->view($this->getEntityHelper()
                         ->getSettingsManager());
    }

    public function save()
    {
        $settings = &$_REQUEST['smartling_settings'];

        $newSecretKey = &$settings['secretKey'];

        $profileId = (int)($settings['id'] ? : 0);

        $settingsManager = $this->getEntityHelper()
                                ->getSettingsManager();

        if (0 === $profileId) {
            $profile = $settingsManager->createProfile([]);
        } else {
            $profiles = $settingsManager->getEntityById($profileId);

            /**
             * @var ConfigurationProfileEntity $profile
             */
            $profile = ArrayHelper::first($profiles);
        }

        if (array_key_exists('profileName', $settings)) {
            $profile->setProfileName($settings['profileName']);
        }


        if (array_key_exists('active', $settings)) {
            $profile->setIsActive($settings['active']);
        }

        if (array_key_exists('projectId', $settings)) {
            $profile->setProjectId($settings['projectId']);
        }

        if (array_key_exists('userIdentifier', $settings)) {
            $profile->setUserIdentifier($settings['userIdentifier']);
        }

        if (
            array_key_exists('secretKey', $settings)
            && '' !== trim($settings['secretKey'])
            && !is_null($settings['secretKey'])
        ) {
            $profile->setSecretKey($settings['secretKey']);
        }


        if (array_key_exists('retrievalType', $settings)) {
            $profile->setRetrievalType($settings['retrievalType']);
        }

        if (array_key_exists('uploadOnUpdate', $settings)) {
            $profile->setUploadOnUpdate($settings['uploadOnUpdate']);
        }

        if (array_key_exists('cloneAttachment', $settings)) {
            $profile->setCloneAttachment($settings['cloneAttachment']);
        }

        if (array_key_exists('always_sync_images_on_upload', $settings)) {
            $profile->setAlwaysSyncImagesOnUpload($settings['always_sync_images_on_upload']);
        }

        if (array_key_exists('download_on_change', $settings)) {
            $profile->setDownloadOnChange($settings['download_on_change']);
        }

        if (array_key_exists('publish_completed', $settings)) {
            $profile->setPublishCompleted($settings['publish_completed']);
        }

        if (array_key_exists('clean_metadata_on_download', $settings)) {
            $profile->setCleanMetadataOnDownload($settings['clean_metadata_on_download']);
        }

        if (array_key_exists('callbackUrl', $settings)) {
            $profile->setCallBackUrl($settings['callbackUrl'] === 'on');
        }

        if (array_key_exists('autoAuthorize', $settings)) {
            $profile->setAutoAuthorize('on' === $settings['autoAuthorize']);
        } else {
            $profile->setAutoAuthorize(false);
        }

        if (array_key_exists('filter_skip', $settings)) {
            $profile->setFilterSkip($settings['filter_skip']);
        }

        if (array_key_exists('filter_copy_by_field_name', $settings)) {
            $profile->setFilterCopyByFieldName($settings['filter_copy_by_field_name']);
        }

        if (array_key_exists('filter_copy_by_field_value_regex', $settings)) {
            $profile->setFilterCopyByFieldValueRegex(stripslashes($settings['filter_copy_by_field_value_regex']));
        }

        if (array_key_exists('filter_flag_seo', $settings)) {
            $profile->setFilterFlagSeo($settings['filter_flag_seo']);
        }

        if (array_key_exists('defaultLocale', $settings)) {
            $defaultBlogId = (int)$settings['defaultLocale'];

            $locale = new Locale();
            $locale->setBlogId($defaultBlogId);
            $locale->setLabel(
                $this->getEntityHelper()
                     ->getSiteHelper()
                     ->getBlogLabelById(
                         $this->getEntityHelper()
                              ->getConnector(),
                         $defaultBlogId
                     )
            );

            $profile->setOriginalBlogId($locale);

        }

        if (array_key_exists('targetLocales', $settings)) {
            $locales = [];

            foreach ($settings['targetLocales'] as $blogId => $settings) {
                try {
                    $tLocale = new TargetLocale();
                    $tLocale->setBlogId($blogId);
                    $tLocale->setLabel($this->getEntityHelper()
                                            ->getSiteHelper()
                                            ->getBlogLabelById($this->getEntityHelper()
                                                                    ->getConnector(),
                                                $blogId));
                    $tLocale->setEnabled(array_key_exists('enabled', $settings) && 'on' === $settings['enabled']);
                    $tLocale->setSmartlingLocale(array_key_exists('target', $settings) ? $settings['target'] : -1);

                    $locales[] = $tLocale;
                } catch (BlogNotFoundException $e) {
                    $this->getLogger()->warning($e->getMessage());
                }
            }

            $profile->setTargetLocales($locales);
        }

        $settingsManager->storeEntity($profile);

        wp_redirect(get_site_url() . '/wp-admin/admin.php?page=smartling_configuration_profile_list');
    }
}