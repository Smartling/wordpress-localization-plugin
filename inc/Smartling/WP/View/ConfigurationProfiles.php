<?php

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Services\GlobalSettingsManager;
use Smartling\WP\Controller\ConfigurationProfilesWidget;
use Smartling\WP\Table\QueueManagerTableWidget;
use Symfony\Component\Yaml\Yaml;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>
<div class="wrap">
    <h2><?= get_admin_page_title(); ?></h2>
    <?php
    $configurationProfilesTable = $data['profilesTable'];
    /**
     * @var ConfigurationProfilesWidget $configurationProfilesTable
     */
    $configurationProfilesTable->prepare_items();

    /**
     * @var QueueManagerTableWidget $cnqTable
     */
    $cnqTable = $data['cnqTable'];
    $cnqTable->prepare_items();
    ?>
    <div id="icon-users" class="icon32"><br/></div>


    <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
    <form id="configuration-profiles-list" method="get">

        <?= $configurationProfilesTable->renderNewProfileButton(); ?>
        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="smartling_configuration_profile_setup"/>
        <input type="hidden" name="profile" value="0"/>
        <!-- Now we can render the completed list table -->
        <?php $configurationProfilesTable->display(); ?>
    </form>
    <p></p>
    <h2><?= __('Crons and Queues'); ?></h2>
    <?php $cnqTable->display(); ?>
    <p>
    <h2><?= __('Log file'); ?></h2>
    <ul>
        <li>
            <a class="button action" href="<?= get_site_url(); ?>/wp-admin/admin-post.php?action=smartling_download_log_file">
                <?= vsprintf(__('Download current log file ( <strong>%s</strong> ).'),[\Smartling\Bootstrap::getCurrentLogFileSize()]); ?>
            </a>
        </li>
        <li>
            <a href="<?= get_site_url(); ?>/wp-admin/admin-post.php?action=smartling_zerolength_log_file"><?= __('DELETE current log file.'); ?></a>
        </li>

        <div class="update-nag">
            <p>
                <?= __("<strong>Warning!</strong><br/>Do not modify the next setting unless you are a Wordpress expert and fully understand the purpose of this setting.<br>"   ); ?>
            </p>
            <p>
                <a href="javascript:void(0)"
                   class="toggleExpert"><strong><?= __('Show Expert Settings'); ?></strong></a>
            </p>

            <div class="toggleExpert hidden">
                <table>
                    <tr>
                        <th colspan="2" class="center">Self-diagnostics configuration</th>
                    </tr>
                    <tr>

                        <th>
                            <label for="selfCheckDisabled">Skip extended environment diagnostics on page load</label>
                        </th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag(
                                'select',
                                HtmlTagGeneratorHelper::renderSelectOptions(
                                    GlobalSettingsManager::getSkipSelfCheck(),
                                    [
                                        0 => 'No',
                                        1 => 'Yes',
                                    ]),
                                [
                                    'id'   => 'selfCheckDisabled',
                                    'name' => 'selfCheckDisabled',
                                ]
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="center">Runtime logging configuration</th>
                    </tr>
                    <tr>
                        <th><label for="disableLogging">Enable logging</label></th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag(
                                'select',
                                HtmlTagGeneratorHelper::renderSelectOptions(
                                    GlobalSettingsManager::getDisableLogging(),
                                    [
                                        0 => 'Yes',
                                        1 => 'No',
                                    ]),
                                [
                                    'id'   => 'disableLogging',
                                    'name' => 'disableLogging',
                                ]
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="loggingPath">Logging Path</label></th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag('input', '', [
                                'type'  => 'text',
                                'value' => \Smartling\Bootstrap::getLogFileName(false),
                                'id'    => 'loggingPath',
                            ]);
                            ?>
                            <a href="javascript:void(0)" id="resetLogPath" data-path="<?= GlobalSettingsManager::getLogFileSpecDefault(); ?>">reset to defaults</a>
                            | note, absolute or relative path may be used. Current path points to <em>/wp-admin</em> folder.<br/>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="loggingCustomization">Logging Customization</label></th>
                        <td>
                            <textarea id="loggingCustomization"><?= stripslashes(Yaml::dump( GlobalSettingsManager::getLoggingCustomization())); ?></textarea>

                            <div id="defaultLoggingCustomizations" style="display: none"><?= Yaml::dump(GlobalSettingsManager::getLoggingCustomizationDefault());?></div>

                        <a href="javascript:void(0)" id="resetLoggingCustomization">reset to defaults</a>
                        | note, levels can be debug, info, notice, warning, error, critical, alert, emergency.<br/>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="center">ACF Runtime settings</th>
                    </tr>
                    <tr>
                        <th><label for="disableACF">Disable ACF support</label></th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag(
                                'select',
                                HtmlTagGeneratorHelper::renderSelectOptions(
                                        GlobalSettingsManager::getDisableACF(),
                                    [
                                        0 => 'No',
                                        1 => 'Yes',
                                    ]),
                                [
                                    'id'   => 'disableACF',
                                    'name' => 'disableACF',
                                ]
                            );
                            ?>
                            <br />Disabling of ACF plugin increases speed but disables support of ACF plugin content and handling of content relations. It is highly recommended to keep ACF plugin support enabled.<br/>
                        </td>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="disableDBLookup">Disable ACF configuration database lookup (not recommended)</label></th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag(
                                'select',
                                HtmlTagGeneratorHelper::renderSelectOptions(
                                    GlobalSettingsManager::getDisableAcfDbLookup(),
                                    [
                                        0 => 'No',
                                        1 => 'Yes',
                                    ]),
                                [
                                    'id'   => 'disableDBLookup',
                                    'name' => 'disableDBLookup',
                                ]
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="center">User Interface Customizations</th>
                    </tr>
                    <tr>
                        <th><label for="loggingPath">Elements per page</label></th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag('input', '', [
                                'type'  => 'text',
                                'value' => GlobalSettingsManager::getPageSizeRuntime(),
                                'id'    => 'pageSize',
                            ]);
                            ?>
                            <a href="javascript:void(0)" id="resetPageSize" data-default="<?= GlobalSettingsManager::getPageSizeDefault(); ?>">reset to defaults</a>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="<?= GlobalSettingsManager::SMARTLING_FRONTEND_GENERATE_LOCK_IDS ?>">Generate lock ids on editing content</label></th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag(
                                'select',
                                HtmlTagGeneratorHelper::renderSelectOptions(
                                    GlobalSettingsManager::isGenerateLockIdsFrontend() ? 1 : 0,
                                    [
                                        0 => 'Disabled',
                                        1 => 'Enabled',
                                    ]),
                                [
                                    'id' => GlobalSettingsManager::SMARTLING_FRONTEND_GENERATE_LOCK_IDS,
                                    'name' => GlobalSettingsManager::SMARTLING_FRONTEND_GENERATE_LOCK_IDS,
                                ]
                            );
                            ?>
                            <br /><a href="javascript:void(0)" id="resetGenerateLockIds" data-default="<?= GlobalSettingsManager::isGenerateLockIdsFrontendDefault() ?>">reset to defaults</a>
                            <br /><?= __('Automatically generate smartlingLockId attribute for Gutenberg blocks when saving content')?><br/>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="handleRelationsManually">Handle relations</label></th>
                        <td>
                            <?=
                            \Smartling\Helpers\HtmlTagGeneratorHelper::tag(
                                'select',
                                \Smartling\Helpers\HtmlTagGeneratorHelper::renderSelectOptions(
                                    GlobalSettingsManager::getHandleRelationsManually(),
                                    [
                                        0 => 'Automatically',
                                        1 => 'Manually',
                                    ]),
                                [
                                    'id'   => 'handleRelationsManually',
                                    'name' => 'handleRelationsManually',
                                ]
                            );
                            ?>
                            <br /><a href="javascript:void(0)" id="resetHandleRelationsManually" data-default="<?= GlobalSettingsManager::getHandleRelationsManuallyDefault(); ?>">reset to defaults</a>
                            <br /><?= __('Note, that switching handling of relations to Manual changes how plugin will work with related assets. An example, if &quot;post&quot; has category and image, then plugin will not create them on a target site. As a result, your translated post layout may look broken')?><br/>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="<?= GlobalSettingsManager::RELATED_CHECKBOX_STATE ?>">
                                Upload widget send related content for translation checkbox default state
                            </label></th>
                        <td>
                            <?= HtmlTagGeneratorHelper::tag(
                                'select',
                                HtmlTagGeneratorHelper::renderSelectOptions(
                                    GlobalSettingsManager::getRelatedContentCheckboxState(),
                                    [
                                        0 => 'Unchecked',
                                        1 => 'Checked',
                                    ]),
                                [
                                    'id'   => GlobalSettingsManager::RELATED_CHECKBOX_STATE,
                                    'name' => GlobalSettingsManager::RELATED_CHECKBOX_STATE,
                                ]
                            )
                            ?>
                            <br /><a href="javascript:void(0)" id="resetRelatedContentCheckbox" data-default="<?= GlobalSettingsManager::getRelatedContentCheckboxDefault(); ?>">reset to defaults</a>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="loggingPath">Enable Fine Tuning</label></th>
                        <td>
                            <?=
                            HtmlTagGeneratorHelper::tag(
                                'select',
                                HtmlTagGeneratorHelper::renderSelectOptions(
                                    GlobalSettingsManager::getFilterUiVisible(),
                                    [
                                        0 => 'No',
                                        1 => 'Yes',
                                    ]),
                                [
                                    'id'   => 'enableFilterUI',
                                    'name' => 'enableFilterUI',
                                ]
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="center">
                            <a class="button action saveExpertSkip"
                               actionUrl="<?= admin_url('admin-ajax.php') ?>?action=smartling_expert_global_settings_update" href="javascript:void(0)">Apply changes</a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </ul>
    </p>
</div>