<?php
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\WP\Controller\ConfigurationProfilesWidget;
/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>
<div class = "wrap" >
	<h2 ><?= get_admin_page_title(); ?></h2 >
	<?php
	$configurationProfilesTable = $data;
	/**
	 * @var ConfigurationProfilesWidget $configurationProfilesTable
	 */
	$configurationProfilesTable->prepare_items();
	?>
	<div id = "icon-users" class = "icon32" ><br /></div >


	<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
	<form id = "submissions-filter" method = "get" >

		<?= $configurationProfilesTable->renderNewProfileButton(); ?>
		<!-- For plugins, we also need to ensure that the form posts back to our current page -->
		<input type = "hidden" name = "page" value = "smartling_configuration_profile_setup" />
		<input type = "hidden" name = "profile" value = "0" />
		<!-- Now we can render the completed list table -->
		<?php $configurationProfilesTable->display(); ?>
	</form >
	<p >
	<ul >
		<li >
			<a href = "/wp-admin/admin-post.php?action=smartling_run_cron" target = "_blank" >
				<?= __( 'Trigger cron tasks (only for smartling connector, opens in a new window)' ); ?>
			</a >
		</li >
		<li >
			<a href = "/wp-admin/admin-post.php?action=smartling_download_log_file" >
				<?= __( 'Download current log file' ); ?>
			</a >
		</li >
	</ul >
	</p>
</div >