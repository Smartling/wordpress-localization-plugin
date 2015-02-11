<div id = "smartling-post-widget" >

	<div class = "fields" >
		<h3 >Translate this post into:</h3 >
		<?php

		/**
		 * @var TargetLocale[] $locales
		 */
		use Smartling\Settings\TargetLocale;

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
					<input type = "hidden" name = "smartling_post_widget[locales][<?= $locale->getBlog(); ?>][blog]"
					       value = "<?= $locale->getBlog(); ?>" >
					<input type = "hidden" name = "smartling_post_widget[locales][<?= $locale->getBlog(); ?>][locale]"
					       value = "<?= $locale->getLocale(); ?>" >
					<input type = "checkbox" <?= $checked; ?>
					       name = "smartling_post_widget[locales][<?= $locale->getBlog(); ?>][enabled]" >
					<span ><?= $locale->getLocale(); ?></span >
				</label >
				<?php if ( $value ) { ?>
					<span title = "<?= __($item->getStatus()); ?>" class = "widget-btn <?= $item->getStatusColor() ?>" ><span ><?= $item->getCompletionPercentage() ?>%</span ></span >
				<?php } ?>
			</p >

		<?php } ?>
	</div >
	<div class = "bottom" >
		<input type = "submit" value = "<?= __( 'Send to Smartling' ); ?>" class = "button button-primary" id = "submit"
		       name = "submit" >
		<input type = "submit" value = "<?= __( 'Download' ); ?>" class = "button button-primary" id = "submit"
		       name = "submit" >
	</div >
</div >