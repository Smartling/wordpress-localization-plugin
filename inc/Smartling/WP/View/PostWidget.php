<div id = "smartling-post-widget" >

	<div class = "fields" >
		<h3 >Translate this post into:</h3 >
		<?php

		/**
		 * @var array $locales
		 */
		$locales = $this->getPluginInfo()->getSettingsManager()->getLocales()->getTargetLocales();

		foreach ( $locales as  $locale ) {
			$value      = false;
			$checked    = '';
			$status     = '';
			$submission = null;
			if ( null !== $data['submissions'] ) {
				foreach ( $data['submissions'] as $item ) {
					if($item->getTargetBlog() === $locale->getBlog()) {
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
					<input type = "hidden" name = "smartling_post_widget[locales][<?php echo $locale->getBlog(); ?>][blog]"
					       value = "<?php echo $locale->getBlog(); ?>" >
					<input type = "hidden" name = "smartling_post_widget[locales][<?php echo $locale->getBlog(); ?>][locale]"
					       value = "<?php echo $locale->getLocale(); ?>" >
					<input type = "checkbox" <?php echo $checked; ?>
					       name = "smartling_post_widget[locales][<?php echo $locale->getBlog(); ?>][enabled]" >
					<span ><?php echo $locale->getLocale(); ?></span >
				</label >
				<?php if ( $value ) { ?>
					<span title = "In Progress" class = "widget-btn <?php echo $status ?>" ><span><?php echo $percent?>%</span></span >
				<?php } ?>
			</p >

		<?php } ?>
	</div >
	<div class = "bottom" >
		<input type = "submit" value = "Send to Smartling" class = "button button-primary" id = "submit"
		       name = "submit" >
		<input type = "submit" value = "Download" class = "button button-primary" id = "submit" name = "submit" >
	</div >
</div >