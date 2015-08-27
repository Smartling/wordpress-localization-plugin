<?php
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\PostWidgetController;
use Smartling\WP\WPAbstract;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>
<div id = "smartling-post-widget" >
	<div class = "fields" >
		<h3 ><?= $this->getWidgetHeader(); ?></h3 >
		<?= WPAbstract::checkUncheckBlock(); ?>
		<?php
		$nameKey = PostWidgetController::WIDGET_DATA_NAME;

		/**
		 * @var TargetLocale[] $locales
		 */
		$locales = $data['profile']->getTargetLocales();

		foreach ( $locales as $locale ) {
			/**
			 * @var TargetLocale $locale
			 */
			if ( ! $locale->isEnabled() ) {
				continue;
			}

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
					if ( $item->getTargetBlogId() === $locale->getBlogId() ) {
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
			<div class = "smtPostWidget-rowWrapper" >
				<div class = "smtPostWidget-row" >
					<?= WPAbstract::localeSelectionCheckboxBlock(
						$nameKey,
						$locale->getBlogId(),
						$locale->getLabel(),
						$value
					); ?>
				</div >
				<div class = "smtPostWidget-progress" >
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
				</div >
			</div >
		<?php } ?>
	</div >
	<div class = "smtPostWidget-submitBlock" >
		<?= WPAbstract::submitBlock(); ?>
	</div >
</div >