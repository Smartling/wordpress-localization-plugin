<?php

/**
 * Provide a dashboard view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
	<h2><?php echo get_admin_page_title() ?></h2>
	<div class="display-errors"></div>
	<form id="smartling-form" action="admin-post.php" method="POST">
		<input type="hidden" name="action" value="smartling_settings">
		<?php wp_nonce_field( 'smartling_connector_settings', 'smartling_connector_nonce'); ?>
		<?php wp_referer_field(); ?>
		<h3>Account info</h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">API URL</th>
					<td>
						<input type="text" name="smartling_settings[field_api_url]" value="<?php echo get_site_option( 'field_api_url' ); ?>">		
						<br>
						<small>Set api url. Default: https://capi.smartling.com/v1</small>
					</td>
				</tr>
				<tr>
					<th scope="row">Project ID</th>
					<td>
						<input type="text" name="smartling_settings[field_project_id]" value="<?php echo get_site_option( 'field_project_id' ); ?>">		
					</td>
				</tr>
				<tr>
					<th scope="row">Key</th>
					<td>			
						<input type="text" id="api_key" name="api_key" value="">
						<input type="hidden" name="smartling_settings[field_project_key]" value="<?php echo get_site_option( 'field_project_key' ); ?>">
						<br>
						<?php if (get_site_option( 'field_project_key' )): ?>
							<small>Current Key: <?php echo substr(get_site_option( 'field_project_key' ),0,-4) . '****' ?></small>
						<?php endif ?>
					</td>
				</tr>
				<tr>
					<th scope="row">Retrieval Type</th>
					<td>
						<?php 
							$option  = get_site_option('field_project_type');
							$buttons = array('pseudo','published','pending');

							for ($i=0; $i < 3; $i++) : 

								$checked = $option == false ? 'published' : $option;
								?>

								<label class="radio-label">
									<p>
										<input type="radio" <?php echo $buttons[$i] == $checked ? 'checked="checked"' : ''; ?> name="smartling_settings[field_project_type]" value="<?php echo $buttons[$i]; ?>">
										<?php echo $buttons[$i]; ?>
									</p>
								</label>
								<br>

							<?php endfor; ?>
						
						<small>Param for download translate.</small>
					</td>
				</tr>
				<tr>
					<th scope="row">Target Locales</th>
					<?php $locales = $this->get_target_locales(); ?>
					<td>
					<?php 
						$default = get_site_option('field_default_locale');

						foreach ($locales as $key => $value):
							$option = get_site_option('field_project_locales_'.$value, 'false');
							$short  = get_site_option('field_shortcut_'.$value, ' ');
							$display = $default == $value ? 'style="display:none"' : 'style="display:block;" ';
							$checked = $option == 'true' ? 'checked="checked"': '';

						?>

							<label class="radio-label" <?php echo $display; ?>>
								<p class="plugin-locales">
									<input type="hidden" name="smartling_settings[field_project_locales_<?php echo $value; ?>]" value="<?php echo $option; ?>">
									<input type="checkbox" <?php echo $checked; ?> name="field_project_locales_<?php echo $value; ?>">
									<span><?php echo $value; ?></span>
									<input type="text" name="smartling_settings[field_shortcut_<?php echo $value; ?>]" value="<?php echo $short; ?>">
								</p>
							</label>

						<?php endforeach; ?>
						

					</td>
				</tr>
				<tr>
					<th scope="row">Default Locale</th>
					<td>
						<?php $default =  get_site_option('field_default_locale'); ?>

						<p>Site default language is: <?php echo $default; ?></p>
						<p>
							<a href="#" id="chenge-default-locale">Change default locale</a>
						</p>
						<br>
						<?php $locales = $this->get_target_locales(); ?>
						<select name="smartling_settings[field_default_locale]" id="default-locales">
							<?php foreach ($locales as $key => $value): 

								$checked = $default == $value ? 'selected' : '';
							?>
								<option value="<?php echo $value; ?>" <?php echo $checked; ?>> <?php echo $value; ?> </option>

							<?php endforeach ; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Callback URL</th>
					<td>				
						<label class="radio-label">
							<p>
								<?php 
									$option = get_site_option('field_project_callback', 'false' );
									$checked = $option == 'true' ? 'checked="checked"' : ''; 
								?>
								<input type="hidden" name="smartling_settings[field_project_callback]" value="<?php echo $option; ?>">
								<input type="checkbox" name="field_project_callback_visual" <?php echo $checked; ?> >
								Use smartling callback: /smartling/callback/%cron_key
							</p>
						</label>
					<br>

					</td>
				</tr>
				<tr>
					<th scope="row">Auto authorize</th>
					<td>				
						<label class="radio-label">
							<p>
								<?php 
									$option = get_site_option('field_project_authorize');
									$checked = $option == 'true' ? 'checked="checked"' : ''; 
								 ?>
								<input type="hidden" name="smartling_settings[field_project_authorize]" value="<?php echo $option; ?>">
								<input type="checkbox" name="field_project_authorize" <?php echo $checked; ?> >
								Auto authorize content
							</p>
						</label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button(); ?>
	</form>
</div>