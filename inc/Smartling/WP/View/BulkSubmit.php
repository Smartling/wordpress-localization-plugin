<?php
use Smartling\WP\WPAbstract;
use Smartling\Settings\TargetLocale;

?>
<div class = "wrap" >
	<style >
		table.form-table th {
			display : inline-table;
		}

		td.bulkActionCb {
			padding-left : 18px;
		}
	</style >
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


	<table class = "form-table" >
		<tr >
			<td >
				<form id = "bulk-submit-type-filter" method = "get" >
					<input type = "hidden" name = "page" value = "<?= $_REQUEST['page']; ?>" />
					<?= $bulkSubmitTable->contentTypeSelectRender(); ?>
					<?= $bulkSubmitTable->renderSubmitButton( __( 'Apply Filter' ) ); ?>
				</form >
			</td >
		</tr >
	</table >

	<form id = "bulk-submit-main" method = "post" >
		<table >
			<tr >
				<td >
					<h3 ><?= __( 'Translate into:' ); ?></h3 >
					<?= WPAbstract::checkUncheckBlock(); ?>
					<?php
					/**
					 * @var TargetLocale[] $locales
					 */
					$locales = $this->getPluginInfo()->getSettingsManager()->getLocales()->getTargetLocales();

					foreach ( $locales as $locale ) {
						/**
						 * @var TargetLocale $locale
						 */
						if ( ! $locale->getEnabled() ) {
							continue;
						}
						?>
						<p >
							<?= WPAbstract::localeSelectionCheckboxBlock(
								'bulk-submit-locales',
								$locale->getBlog(),
								$locale->getLocale(),
								false
							); ?>
						</p >
					<?php } ?>
				</td >
			</tr >
		</table >
		<input type = "hidden" name = "content-type" id = "ct" value = "" />
		<input type = "hidden" name = "page" value = "<?= $_REQUEST['page']; ?>" />
		<input type = "hidden" name = "action" value = "send" />
		<?php $bulkSubmitTable->display() ?>
		<?= WPAbstract::sendButton( 'sent-to-smartling-bulk' ); ?>
	</form >

</div >