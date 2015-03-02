<?php
use Smartling\WP\WPAbstract;
use Smartling\Settings\TargetLocale;

?>
<div class = "wrap" >
	<h2 ><?= get_admin_page_title(); ?></h2 >

	<div class = "display-errors" ></div >
	<?php
	use Smartling\WP\View\BulkSubmitTableWidget;

	$bulkSubmitTable = $data;
	/**
	 * @var BulkSubmitTableWidget $submissionsTable
	 */
	$bulkSubmitTable->prepare_items();
	?>
	<div id = "icon-users" class = "icon32" ><br /></div >

	<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
	<form id = "bulk-submit-filter" method = "get" >
		<table width = "100%" >
			<tr >
				<td style = "text-align: left;" ><p >
						<?= $bulkSubmitTable->contentTypeSelectRender(); ?>
						<?= $bulkSubmitTable->renderSubmitButton( __( 'Apply Filter' ) ); ?>
					</p ></td >
				<td style = "display: none;" ><?php $bulkSubmitTable->search_box( __( 'Search' ), 's' ); ?></td >
			</tr >
			<tr >
				<td >
					<h3 ><?= __( 'Translate this post into:' ); ?></h3 >
					<?= WPAbstract::checkUncheckBlock(); ?>
					<?php
					/**
					 * @var TargetLocale[] $locales
					 */
					$locales = $this->getPluginInfo()->getSettingsManager()->getLocales()->getTargetLocales();

					foreach ( $locales as $locale ) {
						?>
						<p >
							<?= WPAbstract::localeSelectionCheckboxBlock(
								"bulk-submit-locales",
								$locale->getBlog(),
								$locale->getLocale(),
								false
							); ?>
						</p >
					<?php } ?>
				</td >
			</tr >
		</table >


		<!-- For plugins, we also need to ensure that the form posts back to our current page -->
		<input type = "hidden" name = "page" value = "<?= $_REQUEST['page']; ?>" />
		<!-- Now we can render the completed list table -->
		<?php $bulkSubmitTable->display() ?>
	</form >
</div >