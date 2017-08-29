<?php
use Smartling\WP\Controller\ConfigurationProfilesWidget;
use Smartling\WP\Table\QueueManagerTableWidget;

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
                            \Smartling\Helpers\HtmlTagGeneratorHelper::tag(
                                'select',
                                \Smartling\Helpers\HtmlTagGeneratorHelper::renderSelectOptions(
                                    \Smartling\Helpers\SimpleStorageHelper::get(\Smartling\Bootstrap::SELF_CHECK_IDENTIFIER, 0),
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
                            \Smartling\Helpers\HtmlTagGeneratorHelper::tag(
                                'select',
                                \Smartling\Helpers\HtmlTagGeneratorHelper::renderSelectOptions(
                                    \Smartling\Helpers\SimpleStorageHelper::get(\Smartling\Bootstrap::DISABLE_LOGGING, 0),
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
                            \Smartling\Helpers\HtmlTagGeneratorHelper::tag('input', '', [
                                'type'  => 'text',
                                'value' => \Smartling\Bootstrap::getLogFileName(false),
                                'id'    => 'loggingPath',
                            ]);
                            ?>
                            <a href="javascript:void(0)" id="resetLogPath" data-path="<?= \Smartling\Bootstrap::getLogFileName(false, true); ?>">reset to defaults</a>
                            | note, absolute or relative path may be used. Current path points to <em>/wp-admin</em> folder.<br/>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2" class="center">User Interface Customizations</th>
                    </tr>
                    <tr>
                        <th><label for="loggingPath">Elements per page</label></th>
                        <td>
                            <?=
                            \Smartling\Helpers\HtmlTagGeneratorHelper::tag('input', '', [
                                'type'  => 'text',
                                'value' => \Smartling\Bootstrap::getPageSize(),
                                'id'    => 'pageSize',
                            ]);
                            ?>
                            <a href="javascript:void(0)" id="resetPageSize" data-default="<?= \Smartling\Bootstrap::getPageSize( true); ?>">reset to defaults</a>
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