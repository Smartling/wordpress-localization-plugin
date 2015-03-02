<?php
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\PageWidgetController;
use Smartling\WP\WPAbstract;

?>
<div id = "smartling-post-widget" >
	<div class = "fields" >
		<h3 ><?= __( 'Translate this page into:' ); ?></h3 >
		<?= WPAbstract::checkUncheckBlock(); ?>
		<?php
		$nameKey = PageWidgetController::WIDGET_DATA_NAME;

		/**
		 * @var TargetLocale[] $locales
		 */
		$locales = $this->getPluginInfo()->getSettingsManager()->getLocales()->getTargetLocales();

		foreach ( $locales as $locale ) {
			$value = false;

			$status      = '';
			$submission  = null;
			$statusValue = null;
			$id          = null;
			if ( null !== $data['submissions'] ) {
				foreach ( $data['submissions'] as $item ) {
					/**
					 * @var SubmissionEntity $item
					 */
					if ( $item->getTargetBlog() === $locale->getBlog() ) {
						$value       = true;
						$statusValue = $item->getStatus();
						$id          = $item->getId();
						$percent     = $item->getCompletionPercentage();
						$status      = $item->getStatusColor();
						break;
					}
				}
			}
			?>
			<p >
				<?= WPAbstract::localeSelectionCheckboxBlock(
					$nameKey,
					$locale->getBlog(),
					$locale->getLocale(),
					$value
				); ?>
				<?php if ( $value ) { ?>
					<?= WPAbstract::localeSelectionTranslationStatusBlock(
						__( $statusValue ),
						$status,
						$percent
					); ?>
					<?= WPAbstract::inputHidden(
						$id
					); ?>
				<?php } ?>
			</p >
		<?php } ?>
	</div >
	<?= WPAbstract::submitBlock(); ?>
</div >