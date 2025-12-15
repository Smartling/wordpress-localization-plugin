<?php

namespace Smartling\WP\Controller;

use Smartling\Bootstrap;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\StringHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\TargetLocale;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

class ConfigurationProfileFormController extends WPAbstract implements WPHookInterface
{
    public const FILTER_FIELD_NAME_REGEXP = 'filter_field_name_regexp';
    public const ERROR_TARGET_LOCALES = 'tl';

    public function wp_enqueue(): void
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

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'wp_enqueue']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('network_admin_menu', [$this, 'menu']);
        add_action('admin_post_smartling_configuration_profile_save', [$this, 'save']);

        if(current_user_can(SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP)) {
            add_action('wp_ajax_' . 'smartling_test_connection', [$this, 'initTestConnectionEndpoint']);
        }
    }

    public function initTestConnectionEndpoint(): void
    {
        $data =& $_POST;

        $result = [
            'status' => 200,
        ];

        $wrapper = Bootstrap::getContainer()->get('api.wrapper.with.retries');

        $settingsManager = Bootstrap::getContainer()->get('manager.settings');

        if (StringHelper::isNullOrEmpty($data['tokenSecret']) && 0 < (int)$data['profileId']) {
            $_profiles = $settingsManager->getEntityById((int)$data['profileId']);
            if (0 < count($_profiles)) {
                $_profile = ArrayHelper::first($_profiles);
                $data['tokenSecret'] = $_profile->getSecretKey();
            }
        }

        $testProfile = $settingsManager->createProfile(
            [
                'auto_authorize' => true,
                'upload_on_update' => true,
                'retrieval_type' => 'published',
                'profile_name' => 'test profile',
                'project_id' => $data['projectId'],
                'user_identifier' => $data['userIdent'],
                'secret_key' => $data['tokenSecret'],
            ]
        );

        try {
            $_result = $wrapper->getSupportedLocales($testProfile);
            $result['locales'] = $_result;
            if (0 === count($_result)) {
                $result['status'] = 400;
            }
        } catch (\Exception $e) {
            $result['status'] = 400;
            $result['message'] = $e->getMessage();
        }

        wp_send_json($result);
    }

    public function menu(): void
    {
        add_submenu_page(
            ConfigurationProfilesController::MENU_SLUG,
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

    public function edit(): void
    {
        $this->view($this->settingsManager);
    }

    public function save(): void
    {
        $settings = &$_REQUEST['smartling_settings'];

        $profileId = (int)($settings['id'] ? : 0);

        if (0 === $profileId) {
            $profile = $this->settingsManager->createProfile([]);
        } else {
            $profiles = $this->settingsManager->getEntityById($profileId);
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

        if (array_key_exists('enable_notifications', $settings)) {
            $profile->setEnableNotifications($settings['enable_notifications']);
        }

        if (array_key_exists('download_on_change', $settings)) {
            $profile->setDownloadOnChange($settings['download_on_change']);
        }

        if (array_key_exists('publish_completed', $settings)) {
            $profile->setChangeAssetStatusOnCompletedTranslation($settings['publish_completed']);
        }

        if (array_key_exists('clean_metadata_on_download', $settings)) {
            $profile->setCleanMetadataOnDownload($settings['clean_metadata_on_download']);
        }

        if (array_key_exists('autoAuthorize', $settings)) {
            $profile->setAutoAuthorize('on' === $settings['autoAuthorize']);
        } else {
            $profile->setAutoAuthorize(false);
        }

        if (array_key_exists(self::FILTER_FIELD_NAME_REGEXP, $settings)) {
            $profile->setFilterFieldNameRegexp( '1' === $settings[self::FILTER_FIELD_NAME_REGEXP]);
        }

        if (array_key_exists('filter_skip', $settings)) {
            $profile->setFilterSkip(stripslashes($settings['filter_skip']));
        }

        if (array_key_exists('filter_copy_by_field_name', $settings)) {
            $profile->setFilterCopyByFieldName(stripslashes($settings['filter_copy_by_field_name']));
        }

        if (array_key_exists('filter_copy_by_field_value_regex', $settings)) {
            $profile->setFilterCopyByFieldValueRegex(stripslashes($settings['filter_copy_by_field_value_regex']));
        }

        if (array_key_exists('filter_flag_seo', $settings)) {
            $profile->setFilterFlagSeo(stripslashes($settings['filter_flag_seo']));
        }

        if (array_key_exists('defaultLocale', $settings)) {
            $defaultBlogId = (int)$settings['defaultLocale'];

            $locale = new Locale();
            $locale->setBlogId($defaultBlogId);
            $locale->setLabel($this->siteHelper->getBlogLabelById($this->localizationPluginProxy, $defaultBlogId));

            $profile->setSourceLocale($locale);
        }

        $usedTargetLocales = [];
        if (array_key_exists('targetLocales', $settings)) {
            $locales = [];

            foreach ($settings['targetLocales'] as $blogId => $settings) {
                try {
                    $tLocale = new TargetLocale();
                    $tLocale->setBlogId($blogId);
                    $tLocale->setLabel($this->siteHelper->getBlogLabelById($this->localizationPluginProxy, $blogId));
                    $enabled = 'on' === $settings['enabled'];
                    $tLocale->setEnabled(array_key_exists('enabled', $settings) && $enabled);
                    $smartlingLocale = array_key_exists('target', $settings) ? $settings['target'] : -1;
                    $tLocale->setSmartlingLocale($smartlingLocale);
                    if ($smartlingLocale !== -1 && $enabled) {
                        $usedTargetLocales[] = $smartlingLocale;
                    }

                    $locales[] = $tLocale;
                } catch (BlogNotFoundException $e) {
                    $this->getLogger()->warning($e->getMessage());
                }
            }

            $profile->setTargetLocales($locales);
        }

        if ($this->validateTargetLocales($usedTargetLocales)) {
            $this->settingsManager->storeEntity($profile);
            wp_redirect(get_admin_url(null, 'admin.php?page=' . ConfigurationProfilesController::MENU_SLUG));
        } elseif ($profile->getId() > 0) {
            wp_redirect(get_admin_url(null, 'admin.php?page=smartling_configuration_profile_setup&error=' . self::ERROR_TARGET_LOCALES . '&action=edit&profile=' . $profile->getId()));
        }
    }

    protected function renderLocales(
        ConfigurationProfileEntity $profile,
        string $displayName,
        int $blogId,
        string $smartlingName = '',
        bool $enabled = false,
    ): string {
        $parts = [];

        $checkboxProperties = [
            'type' => 'checkbox',
            'class' => 'mcheck',
            'name' => sprintf('smartling_settings[targetLocales][%s][enabled]', $blogId),
        ];

        if (true === $enabled) {
            $checkboxProperties['checked'] = 'checked';
        }

        $parts[] = HtmlTagGeneratorHelper::tag('input', '', $checkboxProperties);
        $parts[] = HtmlTagGeneratorHelper::tag('span', htmlspecialchars($displayName));
        $parts = [
            HtmlTagGeneratorHelper::tag('label', implode('', $parts), ['class' => 'radio-label']),
        ];

        $locales = $this->api->getSupportedLocales($profile);

        if (0 === count($locales)) {
            $sLocale = HtmlTagGeneratorHelper::tag(
                'input',
                '',
                [
                    'name' => sprintf('smartling_settings[targetLocales][%s][target]', $blogId),
                    'type' => 'text',
                ]);
        } else {
            $sLocale = HtmlTagGeneratorHelper::tag(
                'select',
                HtmlTagGeneratorHelper::renderSelectOptions(
                    $smartlingName,
                    $locales
                ),
                [
                    'name' => sprintf('smartling_settings[targetLocales][%s][target]', $blogId),
                ]);
        }

        $parts = [
            HtmlTagGeneratorHelper::tag('td', implode('', $parts), ['style' => 'display:table-cell;']),
        ];
        $parts[] = HtmlTagGeneratorHelper::tag('td', $sLocale, [
            'style' => 'display:table-cell',
            'class' => 'targetLocaleSelectCell',
        ]);

        return implode('', $parts);
    }

    private function validateTargetLocales(array $targetLocales): bool {
        $values = array_count_values($targetLocales);
        foreach ($values as $value) {
            if ($value > 1) {
                return false;
            }
        }
        return true;
    }
}
