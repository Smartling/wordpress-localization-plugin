<?php
/**
 * @var PluginInfo $pluginInfo
 */

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\WP\WPAbstract;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();

$pluginInfo = $this->getPluginInfo();
$domain = $pluginInfo->getDomain();

/**
 * @var SettingsManager $settingsManager
 */
$settingsManager = $data;

$profileId = (int)($_GET['profile'] ? : 0);

if (0 === $profileId) {
    $profile = $settingsManager->createProfile([]);
    $defaultFilter = Smartling\Bootstrap::getContainer()->getParameter('field.processor.default');
    $profile->setFilterSkip(implode(PHP_EOL, $defaultFilter['ignore']));
    $profile->setFilterFlagSeo(implode(PHP_EOL, $defaultFilter['key']['seo']));
    $profile->setFilterCopyByFieldName(implode(PHP_EOL, $defaultFilter['copy']['name']));
    $profile->setFilterCopyByFieldValueRegex(implode(PHP_EOL, $defaultFilter['copy']['regexp']));
} else {
    $profiles = $pluginInfo->getSettingsManager()->getEntityById($profileId);

    /**
     * @var ConfigurationProfileEntity $profile
     */
    $profile = reset($profiles);
}
?>

<div class="wrap">
    <h2><?= __(get_admin_page_title(), $domain) ?></h2>
    <form id="smartling-configuration-profile-form" action="/wp-admin/admin-post.php" method="POST">
        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'action',
            'value' => 'smartling_configuration_profile_save',
        ]); ?>

        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'smartling_settings[id]',
            'value' => $profile->getId(),
        ]); ?>

        <?php wp_nonce_field('smartling_connector_settings', 'smartling_connector_nonce'); ?>
        <?php wp_referer_field(); ?>

        <h3><?= __('Account Info', $domain) ?></h3>
        <table class="form-table">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="profileName">
                        <?= __(ConfigurationProfileEntity::getFieldLabel('profile_name'), $domain); ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag('input', '', [
                        'type'                => 'text',
                        'id'                  => 'profileName',
                        'name'                => 'smartling_settings[profileName]',
                        'placeholder'         => __('Set profile name', $domain),
                        'data-msg'            => __('Please set name for profile', $domain),
                        'required'            => 'required',
                        'value'               => htmlentities($profile->getProfileName()),
                    ])
                    ?>
                    <br>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="is_active">
                        <?= ConfigurationProfileEntity::getFieldLabel('is_active'); ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getIsActive(),
                            [
                                '1' => __('Active', $domain),
                                '0' => __('Inactive', $domain),
                            ]
                        ),
                        [
                            'id'   => 'is_active',
                            'name' => 'smartling_settings[active]',
                        ]
                    );
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="project-id">
                        <?= __('Project ID', $domain) ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'input',
                        '',
                        [
                            'type'                => 'text',
                            'name'                => 'smartling_settings[projectId]',
                            'id'                  => 'project-id',
                            'required'            => 'required',
                            'placeholder'         => __('Set project ID', $domain),
                            'data-msg'            => __('Please set project ID', $domain),
                            'data-rule-minlength' => 9,
                            'data-rule-maxlength' => 9,
                            'data-msg-minlength'  => __('Project ID is 9 chars length.', $domain),
                            'data-msg-minlength'  => __('Project ID is 9 chars length.', $domain),

                            'value'               => $profile->getProjectId(),
                        ]
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="userIdentifier">
                        <?= __('User Identifier', $domain) ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'input',
                        '',
                        [
                            'type'        => 'text',
                            'name'        => 'smartling_settings[userIdentifier]',
                            'id'          => 'userIdentifier',
                            'required'    => 'required',
                            'placeholder' => __('Set the User Identifier', $domain),
                            'data-msg'    => __('User Identifier should be set', $domain),
                            'value'       => $profile->getUserIdentifier(),
                        ]
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="secretKey">
                        <?= __('Token Secret', $domain) ?>
                    </label>
                </th>
                <td>
                    <?php
                    $key = $profile->getSecretKey();

                    $tokenOptions = [
                        'type' => 'text',
                        'name' => 'smartling_settings[secretKey]',
                        'id'   => 'secretKey',

                    ];

                    if (\Smartling\Helpers\StringHelper::isNullOrEmpty($key)) {
                        $tokenOptions['required'] = 'required';
                        $tokenOptions['placeholder'] = __('Set the Token Secret', $domain);
                        $tokenOptions['data-msg'] = __('Token Secret should be set', $domain);
                    } else {
                        $tokenOptions['placeholder'] = __('Enter new Token to update', $domain);
                    }

                    ?>
                    <?= HtmlTagGeneratorHelper::tag('input', '', $tokenOptions); ?>
                    <br>
                    <?php if ($key): ?>
                        <small><?= __('Current Key', $domain) ?>: <?= substr($key, 0, -10) . '**********' ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Default Locale', $domain) ?></th>
                <td>
                    <?php
                    $locales = [];
                    foreach ($settingsManager->getSiteHelper()->listBlogs() as $blogId) {
                        try {
                            $locales[$blogId] =
                                $settingsManager->getSiteHelper()
                                    ->getBlogLabelById($settingsManager->getPluginProxy(), $blogId);
                        } catch (BlogNotFoundException $e) {
                        }
                    }
                    ?>
                    <?php if (0 === $profileId): ?>
                        <?php
                        $tagOptions = ['prompt' => __('Please select main locale', $domain)];
                        $options = HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getOriginalBlogId()->getBlogId(),
                            $locales,
                            $tagOptions
                        );
                        ?>
                        <?= HtmlTagGeneratorHelper::tag(
                            'select',
                            $options,
                            ['name'     => 'smartling_settings[defaultLocale]',
                             'required' => 'required',
                             'id'       => 'default-locales-new',]
                        ); ?>
                    <?php else: ?>
                        <p>
                            <?= __('Site default language is: ', $domain) ?>
                            <strong><?= $profile->getOriginalBlogId()->getLabel() ?></strong>
                        </p>
                        <p>
                            <a href="#" id="change-default-locale"><?= __('Change default locale', $domain) ?></a>
                        </p>
                        <br/>
                        <?= HtmlTagGeneratorHelper::tag(
                            'select',
                            HtmlTagGeneratorHelper::renderSelectOptions(
                                $profile->getOriginalBlogId()->getBlogId(),
                                $locales
                            ),
                            ['name' => 'smartling_settings[defaultLocale]',
                             'id'   => 'default-locales',]
                        ); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Target Locales', $domain) ?></th>
                <td>
                    <?= WPAbstract::checkUncheckBlock(); ?>
                    <table>
                        <?php
                        $targetLocales = $profile->getTargetLocales();
                        foreach ($locales as $blogId => $label) {
                            if ($blogId === $profile->getOriginalBlogId()
                                    ->getBlogId()
                            ) {
                                continue;
                            }

                            $smartlingLocale = -1;
                            $enabled = false;

                            foreach ($targetLocales as $targetLocale) {
                                if ($targetLocale->getBlogId() === $blogId) {
                                    $smartlingLocale = $targetLocale->getSmartlingLocale();
                                    $enabled = $targetLocale->isEnabled();
                                    break;
                                }
                            }
                            ?>

                            <tr>
                                <?= WPAbstract::settingsPageTsargetLocaleCheckbox($profile, $label, $blogId,
                                                                                  $smartlingLocale, $enabled); ?>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Retrieval Type', $domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getRetrievalType(),
                            ConfigurationProfileEntity::getRetrievalTypes()
                        ),
                        ['name' => 'smartling_settings[retrievalType]']);

                    ?>
                    <br/>
                    <small><?= __('Param for download translate', $domain) ?>.
                    </small>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Resubmit changed content', $domain) ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getUploadOnUpdate(),
                            [0 => __('Manually', $domain),
                             1 => __('Automatically', $domain),]

                        ),
                        ['name' => 'smartling_settings[uploadOnUpdate]']);

                    ?>
                    <br/>
                    <small>
                        <?= __('Detect and resubmit to Smartling changes in original content', $domain) ?>.
                    </small>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('auto_authorize'); ?></th>
                <td>
                    <label class="radio-label">
                        <p>
                            <?php
                            $option = $profile->getAutoAuthorize();
                            $checked = $option === true ? 'checked="checked"' : '';
                            ?>
                            <input type="checkbox"
                                   name="smartling_settings[autoAuthorize]" <?= $checked; ?> / >
                            <?= __('Auto authorize content', $domain) ?>
                        </p>
                    </label>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="update-nag">
                        <p>
                            <?= __("<strong>Warning!</strong> 
Updates to these settings will change how content is handled during the translation process.<br>
Please consult before making any changes.<br>", $domain); ?>
                        </p>
                        <p>
                            <a href="javascript:void(0)" class="toggleExpert"><strong><?= __('Show Expert Settings',$domain); ?></strong></a>
                        </p>
                    </div>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_skip'); ?></th>
                <td>
                    <p>Fields listed here will be excluded and not carried over during translation. <br>
                        <small>Hints:<br>
                            <ul class="smartling-list">
                                <li>Each row is a unique field.</li>
                                <li>Fields are case sensitive.</li>
                                <li>Field can be a content object property, meta key name, or a key of a serialized
                                    array.
                                </li>
                            </ul>
                        </small>
                    </p>
                    <textarea wrap="off" cols="45" rows="5" class="nowrap"
                              name="smartling_settings[filter_skip]"><?= trim($profile->getFilterSkip()); ?></textarea>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_copy_by_field_name'); ?></th>
                <td>
                    <p>Fields listed here will be excluded from translation and copied over from the source content.<br>
                        <small>Hints: <br>
                            <ul class="smartling-list">
                                <li>Each row is unique field.</li>
                                <li>Fields are case sensitive.</li>
                                <li>Field can be a content object property, meta key name, or a key of a serialized
                                    array.
                                </li>
                            </ul>
                        </small>
                    </p>
                    <textarea wrap="off" cols="45" rows="5" class="nowrap"
                              name="smartling_settings[filter_copy_by_field_name]"><?= trim($profile->getFilterCopyByFieldName()); ?></textarea>

                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_copy_by_field_value_regex'); ?></th>
                <td>
                    <p>Regular expressions listed here will identify field names to exclude from translation and be
                        copied over from the source content. <br>
                        <small>Hints:<br>
                            <ul class="smartling-list">
                                <li>Each row is a unique regular expression</li>
                                <li>Regular expressions are applied without ignore case modifier.</li>
                            </ul>
                        </small>
                    </p>
                   <textarea wrap="off" cols="45" rows="5" class="nowrap"
                             name="smartling_settings[filter_copy_by_field_value_regex]"><?= trim($profile->getFilterCopyByFieldValueRegex()); ?></textarea>
                </td>
            </tr>
            <tr class="toggleExpert hidden">
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('filter_flag_seo'); ?></th>
                <td>
                    <p>Fields listed here will be identified with a special ‘SEO’ key during translation.<br>
                        <small>Hints:<br>
                            <ul class="smartling-list">
                                <li>Each row is a unique field.</li>
                                <li>Fields are case sensitive.</li>
                            </ul>
                        </small>
                    </p>
                    <textarea wrap="off" cols="45" rows="5" class="nowrap"
                              name="smartling_settings[filter_flag_seo]"><?= trim($profile->getFilterFlagSeo()); ?></textarea>
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>

    </form>
</div>
<script>
    var validator;
    jQuery(document).ready(function () {
        validator = jQuery('#smartling-configuration-profile-form').validate();
        
        
        jQuery('a.toggleExpert').on('click', function (e) {
            jQuery('.toggleExpert').removeClass('hidden');
            jQuery('a.toggleExpert').addClass('hidden');
        })
    });
</script>
