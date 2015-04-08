<?php
use Smartling\WP\Controller\ConfigurationProfilesWidget;

?>
<div class = "wrap" >
	<h2 ><?=

		get_admin_page_title(); ?></h2 >

	<div class = "display-errors" ></div >
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
		<input type = "hidden" name = "profileId" value = "0" />
		<!-- Now we can render the completed list table -->
		<?php $configurationProfilesTable->display(); ?>
	</form >
</div >