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
    <form id="submissions-filter" method="get">

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
            <a href="/wp-admin/admin-post.php?action=smartling_download_log_file"><?= __('Download current log file'); ?></a>
        </li>
        <li>
            <a href="/wp-admin/admin-post.php?action=smartling_zerolength_log_file"><?=
                vsprintf(
                    __('Cleanup current log file (current size: <strong>%s</strong>.)'),
                    [
                        \Smartling\Bootstrap::getCurrentLogFileSize()
                    ]
                ); ?>
            </a>
        </li>
    </ul>
    </p>
</div>