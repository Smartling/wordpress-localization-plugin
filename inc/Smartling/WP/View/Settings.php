<?php
/**
 * @var PluginInfo $pluginInfo
 */
use Smartling\Helpers\PluginInfo;
use Smartling\Settings\TargetLocale;

$pluginInfo = $this->getPluginInfo();

$domain = $pluginInfo->getDomain();

$settingsManager = $pluginInfo->getSettingsManager();
?>

<div class = "wrap" >
	<h2 ><?= get_admin_page_title() ?></h2 >

	<div class = "display-errors" ></div >
	<form id = "smartling-form" action = "admin-post.php" method = "POST" >
		<input type = "hidden" name = "action" value = "smartling_settings" >
		<?php wp_nonce_field( 'smartling_connector_settings', 'smartling_connector_nonce' ); ?>
		<?php wp_referer_field(); ?>
		<h3 ><?= __( 'Account Info', $domain ) ?></h3 >
		<table class = "form-table" >
			<tbody >
			<tr >
				<th scope = "row" ><?= __( 'API Url', $domain ) ?></th >
				<td >
					<input type = "text" name = "smartling_settings[apiUrl]"
					       value = "<?= $settingsManager->getAccountInfo()->getApiUrl(); ?>" >
					<br >
					<small ><?= __( 'Set api url. Default', $domain ) ?>:
						https://api.smartling.com/v1
					</small >
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Project ID', $domain ) ?></th >
				<td >
					<input type = "text" name = "smartling_settings[projectId]"
					       value = "<?= $settingsManager->getAccountInfo()->getProjectId(); ?>" >
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Key', $domain ) ?></th >
				<td >
					<?php $key = $settingsManager->getAccountInfo()->getKey(); ?>
					<input type = "text" id = "api_key" name = "apiKey" value = "" >
					<input type = "hidden" name = "smartling_settings[apiKey]" value = "<?= $key ?>" >
					<br >
					<?php if ( $key ) { ?>
						<small ><?= __( 'Current Key', $domain ) ?>
							: <?= substr( $key, 0, - 10 ) . '**********' ?></small >
					<?php } ?>
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Retrieval Type', $domain ) ?></th >
				<td >
					<?php
					$option  = $settingsManager->getAccountInfo()->getRetrievalType();
					$buttons = $settingsManager->getRetrievalTypes();
					$checked = $option ? $option : $buttons[1];
					foreach ( $buttons as $button ) {
						?>
						<label class = "radio-label" >
							<p >
								<input type = "radio" <?= $button == $checked ? 'checked="checked"' : ''; ?>
								       name = "smartling_settings[retrievalType]" value = "<?= $button; ?>" >
								<?= __( $button, $domain ); ?>
							</p >
						</label >
						<br >

					<?php } ?>

					<small ><?php echo __( 'Param for download translate', $this->getPluginInfo()->getDomain() ) ?>.
					</small >
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Target Locales', $domain ) ?></th >
				<td >
					<?php
					/**
					 * @var array $locales
					 */
					$locales     = $this->getSiteLocales();
					$defaultBlog = $settingsManager->getLocales()->getDefaultBlog();
					/**
					 * @var array $targetLocales
					 */
					$targetLocales = $settingsManager->getLocales()->getTargetLocales();
					foreach ( $locales as $key => $value ) {
						if ( $defaultBlog == $key ) {
							continue;
						}
						$short   = null;
						$checked = '';
						foreach ( $targetLocales as $target ) {
							/**
							 * @var TargetLocale $target
							 */
							if ( $target->getLocale() == $value ) {
								$short   = $target->getTarget();
								$checked = $target->getEnabled() ? 'checked="checked"' : '';
								break;
							}
						}

						?>

						<div >
							<p class = "plugin-locales" >
								<label class = "radio-label" >
									<input type = "checkbox" <?= $checked; ?>
									       name = "smartling_settings[targetLocales][<?= $value; ?>][enabled]" >
									<span ><?= $value; ?></span >
								</label >
								<input type = "text"
								       name = "smartling_settings[targetLocales][<?= $value; ?>][target]"
								       value = "<?= $short; ?>" >
								<input type = "hidden"
								       name = "smartling_settings[targetLocales][<?= $value; ?>][blog]"
								       value = "<?= $key; ?>" >
							</p >
						</div >

					<?php } ?>


				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Default Locale', $domain ) ?></th >
				<td >
					<?php
					$defaultLocale = $settingsManager->getLocales()->getDefaultLocale();
					$defaultBlog   = $settingsManager->getLocales()->getDefaultBlog(); ?>

					<p ><?= __( 'Site default language is', $this->getPluginInfo()->getDomain() ) ?>
						: <?= $defaultLocale; ?></p >

					<p >
						<a href = "#" id = "change-default-locale" ><?= __( 'Change default locale',
								$domain ) ?></a >
					</p >
					<br >
					<?php $locales = $this->getSiteLocales(); ?>
					<select name = "smartling_settings[defaultLocale]" id = "default-locales" >
						<?php foreach ( $locales as $key => $value ) {
							$checked = $defaultBlog == $key ? 'selected' : '';
							?>
							<option
								value = "<?php echo "{$key}-{$value}" ?>" <?php echo $checked; ?>> <?php echo $value; ?> </option >

						<?php } ?>
					</select >
				</td >
			</tr >
			<tr style = "display: none;" >
				<th scope = "row" ><?= __( 'Callback URL', $domain ) ?></th >
				<td >
					<label class = "radio-label" >
						<p >
							<?php
							$option  = $settingsManager->getAccountInfo()->getCallBackUrl();
							$checked = $option == true ? 'checked="checked"' : '';
							?>
							<input type = "checkbox" name = "smartling_settings[callbackUrl]" <?= $checked; ?> >
							<?= __( 'Use smartling callback', $domain ) ?>:
							/smartling/callback/%cron_key
						</p >
					</label >
					<br >

				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Auto authorize', $domain ) ?></th >
				<td >
					<label class = "radio-label" >
						<p >
							<?php
							$option  = $settingsManager->getAccountInfo()->getAutoAuthorize();
							$checked = $option == true ? 'checked="checked"' : '';
							?>
							<input type = "checkbox"
							       name = "smartling_settings[autoAuthorize]" <?= $checked; ?> / >
							<?= __( 'Auto authorize content', $domain ) ?>
						</p >
					</label >
				</td >
			</tr >
			</tbody >
		</table >
		<?php submit_button(); ?>
	</form >
</div >