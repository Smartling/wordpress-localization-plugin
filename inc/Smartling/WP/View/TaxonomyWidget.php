<div id = "smartling-post-widget" >

	<script>
		function sm_check_all()
		{
			var boxes = document.getElementsByClassName('mcheck');
			for (var i = 0; i < boxes.length; i++) { boxes[i].setAttribute('checked','checked'); }
		}

		function sm_uncheck_all()
		{
			var boxes=document.getElementsByClassName('mcheck');
			for (var i = 0; i < boxes.length; i++) { boxes[i].removeAttribute('checked'); }
		}
	</script>

	<h2 >Smartling connector actions</h2 >

	<h3 >Translate this <?= $data['term']->taxonomy; ?> into:</h3 >

	<a href="#" onclick="sm_check_all();return false;">Check All</a> / <a href="#" onclick="sm_uncheck_all();return false;">Uncheck All</a>
	<table class = "form-table" style="width: 400px;" >
		<tbody >

		<?php

		use Smartling\Settings\TargetLocale;
		use Smartling\WP\Controller\TaxonomyWidgetController;

		$nameKey = TaxonomyWidgetController::WIDGET_DATA_NAME;

		/**
		 * @var TargetLocale[] $locales
		 */
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
			?>
			<tr class = "form-field" >
				<td width="200px;">
					<label >
						<input type = "hidden" name = "<?= $nameKey; ?>[locales][<?= $locale->getBlog(); ?>][blog]" value = "<?= $locale->getBlog(); ?>" >
						<input type = "hidden" name = "<?= $nameKey; ?>[locales][<?= $locale->getBlog(); ?>][locale]" value = "<?= $locale->getLocale(); ?>" >
						<input class = "mcheck" type = "checkbox" <?= $checked; ?> name = "<?= $nameKey; ?>[locales][<?= $locale->getBlog(); ?>][enabled]" >
						<span ><?= $locale->getLocale(); ?></span >
					</label >
				</td >
				<td style="text-align: left;">
					<?php if ( $value ) { ?>
						<span title = "<?= __( $item->getStatus() ); ?>"
						      class = "widget-btn <?= $item->getStatusColor() ?>" ><span ><?= $item->getCompletionPercentage() ?>%</span ></span >
					<?php } ?>
				</td >
			</tr >
		<?php } ?>
		</td >
		</tr >



		</tbody >

	</table >
	<?= \Smartling\WP\WPAbstract::submitBlock(); ?>
</div >