<?php
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\TaxonomyWidgetController;
use Smartling\WP\WPAbstract;

?>
<div id = "smartling-post-widget" >
	<h2 >Smartling connector actions</h2 >

	<h3 >Translate this <?= $data['term']->taxonomy; ?> into:</h3 >
	<?= WPAbstract::checkUncheckBlock(); ?>
	<table class = "form-table" style = "width: 400px;" >
		<tbody >
		<?php
		$nameKey = TaxonomyWidgetController::WIDGET_DATA_NAME;

		/**
		 * @var TargetLocale[] $locales
		 */
		$locales = $this->getPluginInfo()->getSettingsManager()->getLocales()->getTargetLocales();

		foreach ( $locales as $locale ) {
			$value      = false;
			$status     = '';
			$submission = null;
			if ( null !== $data['submissions'] ) {
				foreach ( $data['submissions'] as $item ) {
					/**
					 * @var SubmissionEntity $item
					 */
					if ( $item->getTargetBlog() === $locale->getBlog() ) {
						$value   = true;
						$percent = $item->getCompletionPercentage();
						$status  = $item->getStatusColor();
						break;
					}
				}
			}
			?>
			<tr class = "form-field" >
				<td width = "200px;" >
					<?= WPAbstract::localeSelectionCheckboxBlock(
						$nameKey,
						$locale->getBlog(),
						$locale->getLocale(),
						$value
					); ?>
				</td >
				<td style = "text-align: left;" >
					<?php if ( $value ) { ?>
						<?= WPAbstract::localeSelectionTranslationStatusBlock(
							__( $item->getStatus() ),
							$status,
							$percent
						); ?>
					<?php } ?>
				</td >
			</tr >
		<?php } ?>

		</tbody >

	</table >
	<?= WPAbstract::submitBlock(); ?>
</div >