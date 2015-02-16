<div id = "smartling-post-widget" >

	<div class = "fields" >
		<h3 >Translate this post into:</h3 >
		<?php
		/**
		 * @var TargetLocale[] $locales
		 */
		use Smartling\Settings\TargetLocale;
		use Smartling\WP\Controller\PostWidgetController;

		$nameKey = PostWidgetController::WIDGET_DATA_NAME;

		$locales = $this->getPluginInfo()->getSettingsManager()->getLocales()->getTargetLocales();

		foreach ( $locales as $locale ) {
			$value      = false;
			$checked    = '';
			$status     = '';
			$submission = null;
			if ( null !== $data['submissions'] ) {
				foreach ( $data['submissions'] as $item ) {

					if ( $item->getTargetBlog() == $locale->getBlog() ) {
						$value   = true;
						$checked = 'checked="checked"';
						$percent = $item->getCompletionPercentage();
						$status  = $item->getStatusColor();
						break;
					}
				}
			}

			//$value   = $values[0][ 'locale_' . $locale->getLocale() ];
			?>

			<p >
				<label >
					<input type = "hidden" name = "<?= $nameKey; ?>[locales][<?= $locale->getBlog(); ?>][blog]"
					       value = "<?= $locale->getBlog(); ?>" >
					<input type = "hidden" name = "<?= $nameKey; ?>[locales][<?= $locale->getBlog(); ?>][locale]"
					       value = "<?= $locale->getLocale(); ?>" >
					<input type = "checkbox" <?= $checked; ?>
					       name = "<?= $nameKey; ?>[locales][<?= $locale->getBlog(); ?>][enabled]" >
					<span ><?= $locale->getLocale(); ?></span >
				</label >
				<?php if ( $value ) { ?>
					<span title = "<?= __( $item->getStatus() ); ?>" class = "widget-btn <?= $item->getStatusColor() ?>" ><span ><?= $item->getCompletionPercentage() ?>%</span ></span >
				<?php } ?>
			</p >

		<?php } ?>
	</div >
	<?= \Smartling\WP\WPAbstract::submitBlock(); ?>
</div >