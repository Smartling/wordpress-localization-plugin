<?php
/**
 * @var PluginInfo $pluginInfo
 */

use Smartling\Exception\BlogNotFoundException;
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
    $defaultFilter = Smartling\Bootstrap::getContainer()
                                        ->getParameter('field.processor.default');
    $profile->setFilterSkip(implode(PHP_EOL, $defaultFilter['ignore']));
    $profile->setFilterFlagSeo(implode(PHP_EOL, $defaultFilter['key']['seo']));
    $profile->setFilterCopyByFieldName(implode(PHP_EOL, $defaultFilter['copy']['name']));
    $profile->setFilterCopyByFieldValueRegex(implode(PHP_EOL, $defaultFilter['copy']['regexp']));
} else {
    $profiles = $pluginInfo->getSettingsManager()
                           ->getEntityById($profileId);

    /**
     * @var ConfigurationProfileEntity $profile
     */
    $profile = reset($profiles);
}
?>
<div class="wrap">
    <h2><?= get_admin_page_title() ?></h2>


    <form id="smartling-form" action="/wp-admin/admin-post.php" method="POST">
        <input type="hidden" name="action" value="smartling_configuration_profile_save">
        <?php wp_nonce_field('smartling_connector_settings', 'smartling_connector_nonce'); ?>
        <?php wp_referer_field(); ?>
        <input type="hidden" name="smartling_settings[id]" value="<?= (int)$profile->getId() ?>">

        <h3><?= __('Account Info', $domain) ?></h3>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('profile_name'); ?></th>
                <td>
                    <input type="text" name="smartling_settings[profileName]"
                           value="<?= htmlentities($profile->getProfileName()); ?>">
                    <br>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= ConfigurationProfileEntity::getFieldLabel('is_active'); ?></th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $profile->getIsActive(),
                            ['1' => __('Active'), '0' => __('Inactive')]
                        ),
                        ['name' => 'smartling_settings[active]']);
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Project ID', $domain) ?></th>
                <td>
                    <input type="text" name="smartling_settings[projectId]"
                           value="<?= $profile->getProjectId(); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('User Identifier', $domain) ?></th>
                <td>

                    <input type="text" id="user_identifier" name="smartling_settings[userIdentifier]"
                           value="<?= $profile->getUserIdentifier(); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Token Secret', $domain) ?></th>
                <td>
                    <?php $key = $profile->getSecretKey(); ?>
                    <input type="text" id="secret_key" name="smartling_settings[secretKey]" value="">
                    <br>
                    <?php if ($key) { ?>
                        <small><?= __('Current Key', $domain) ?>
                            : <?= substr($key, 0, -10) . '**********' ?></small>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?= __('Default Locale', $domain) ?></th>
                <td>
                    <?php
                    $locales = [];
                    foreach ($settingsManager->getSiteHelper()
                                             ->listBlogs() as $blogId) {

                        try {
                            $locales[$blogId] = $settingsManager
                                ->getSiteHelper()
                                ->getBlogLabelById(
                                    $settingsManager->getPluginProxy(),
                                    $blogId
                                );
                        } catch (BlogNotFoundException $e) {
                        }
                    }
                    ?>
                    <p><?= __('Site default language is: ', $this->getPluginInfo()
                                                                 ->getDomain()) ?>
                        <?= HtmlTagGeneratorHelper::tag('strong',
                            $profile->getOriginalBlogId()
                                    ->getLabel()); ?></p>

                    <p>
                        <a href="#" id="change-default-locale"><?= __('Change default locale',
                                $domain) ?></a>
                    </p>
                    <br>
                    <?= HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions($profile->getOriginalBlogId()
                                                                            ->getBlogId(),
                            $locales),
                        ['name' => 'smartling_settings[defaultLocale]', 'id' => 'default-locales']);
                    ?>
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
                    <small><?php echo __('Param for download translate', $this->getPluginInfo()
                                                                              ->getDomain()) ?>.
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
                            <strong>Warning!</strong> Updates to these settings will change how content is handled
                            during the translation process.<br>
                            Please consult before making any changes.<br>
                        </p>
                    </div>
                </td>
            </tr>
            <tr>
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

            <tr>
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
            <tr>
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
            <tr>
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