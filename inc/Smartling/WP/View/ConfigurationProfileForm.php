<?php
/**
 * @var PluginInfo $pluginInfo
 */

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;

use Smartling\WP\WPAbstract;

$pluginInfo = $this->getPluginInfo();
$domain     = $pluginInfo->getDomain();

/**
 * @var SettingsManager $settingsManager
 */
$settingsManager = $data;

$profileId = (int) ( $_GET['profile'] ? : 0 );

if ( 0 === $profileId ) {
	$profile = $settingsManager->createProfile( array () );
} else {
	$profiles = $pluginInfo->getSettingsManager()->getEntityById( $profileId );

	/**
	 * @var ConfigurationProfileEntity $profile
	 */
	$profile = reset( $profiles );
}
?>
<div class = "wrap" >
	<h2 ><?= get_admin_page_title() ?></h2 >


	<form id = "smartling-form" action = "/wp-admin/admin-post.php" method = "POST" >
		<input type = "hidden" name = "action" value = "smartling_configuration_profile_save" >
		<?php wp_nonce_field( 'smartling_connector_settings', 'smartling_connector_nonce' ); ?>
		<?php wp_referer_field(); ?>
		<input type = "hidden" name = "smartling_settings[id]" value = "<?= (int) $profile->getId() ?>" >

		<h3 ><?= __( 'Account Info', $domain ) ?></h3 >
		<table class = "form-table" >
			<tbody >
			<tr >
				<th scope = "row" ><?= __( 'Profile Name', $domain ) ?></th >
				<td >
					<input type = "text" name = "smartling_settings[profileName]"
					       value = "<?= $profile->getProfileName(); ?>" >
					<br >
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Active', $domain ) ?></th >
				<td >
					<?=
					HtmlTagGeneratorHelper::tag(
						'select',
						HtmlTagGeneratorHelper::renderSelectOptions(
							$profile->getIsActive(),
							array ( '1' => __( 'Active' ), '0' => __( 'Inactive' ) )
						),
						array ( 'name' => 'smartling_settings[active]' ) );
					?>
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'API Url', $domain ) ?></th >
				<td >
					<input type = "text" name = "smartling_settings[apiUrl]"
					       value = "<?= $profile->getApiUrl(); ?>" >
					<br >
					<small ><?= __( 'Set api url. Default', $domain ) ?>:
						https://capi.smartling.com/v1
					</small >
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Project ID', $domain ) ?></th >
				<td >
					<input type = "text" name = "smartling_settings[projectId]"
					       value = "<?= $profile->getProjectId(); ?>" >
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Project Key', $domain ) ?></th >
				<td >
					<?php $key = $profile->getProjectKey(); ?>
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
				<th scope = "row" ><?= __( 'Default Locale', $domain ) ?></th >
				<td >
					<?php
					$locales = array ();
					foreach ( $settingsManager->getSiteHelper()->listBlogs() as $blogId ) {
						$locales[ $blogId ] = $settingsManager
							->getSiteHelper()
							->getBlogLabelById(
								$settingsManager->getPluginProxy(),
								$blogId
							);
					}
					?>
					<p ><?= __( 'Site default language is: ', $this->getPluginInfo()->getDomain() ) ?>
						: <?= $profile->getMainLocale()->getLabel(); ?></p >

					<p >
						<a href = "#" id = "change-default-locale" ><?= __( 'Change default locale',
								$domain ) ?></a >
					</p >
					<br >
					<?= HtmlTagGeneratorHelper::tag(
						'select',
						HtmlTagGeneratorHelper::renderSelectOptions( $profile->getMainLocale()->getBlogId(), $locales ),
						array ( 'name' => 'smartling_settings[defaultLocale]', 'id' => 'default-locales' ) );
					?>
				</td >
			</tr >

			<tr >
				<th scope = "row" ><?= __( 'Target Locales', $domain ) ?></th >
				<td >
					<?= WPAbstract::checkUncheckBlock(); ?>
					<?php
					$targetLocales = $profile->getTargetLocales();
					foreach ( $locales as $blogId => $label ) {
						if ( $blogId === $profile->getMainLocale()->getBlogId() ) {
							continue;
						}

						$smartlingLocale = - 1;
						$enabled         = false;

						foreach ( $targetLocales as $targetLocale ) {
							if ( $targetLocale->getBlogId() == $blogId ) {
								$smartlingLocale = $targetLocale->getSmartlingLocale();
								$enabled         = $targetLocale->isEnabled();
								break;
							}
						}
						?>

						<div >
							<p class = "plugin-locales" >
								<?= WPAbstract::settingsPageTsargetLocaleCheckbox( $profile, $label, $blogId,
									$smartlingLocale, $enabled ); ?>
							</p >
						</div >
					<?php
					}
					?>
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Retrieval Type', $domain ) ?></th >
				<td >
					<?=
					HtmlTagGeneratorHelper::tag(
						'select',
						HtmlTagGeneratorHelper::renderSelectOptions(
							$profile->getRetrievalType(),
							ConfigurationProfileEntity::getRetrievalTypes()
						),
						array ( 'name' => 'smartling_settings[retrievalType]' ) );

					?>
					<br />
					<small ><?php echo __( 'Param for download translate', $this->getPluginInfo()->getDomain() ) ?>.
					</small >
				</td >
			</tr >
			<tr >
				<th scope = "row" ><?= __( 'Auto authorize', $domain ) ?></th >
				<td >
					<label class = "radio-label" >
						<p >
							<?php
							$option  = $profile->getAutoAuthorize();
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