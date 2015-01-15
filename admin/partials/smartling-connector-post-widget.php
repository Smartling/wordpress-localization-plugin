

<div class="">
	<div id="smartling-post-widget">
		
		<div class="fields">
			<p>Translate this post into:</p>
			<?php 
				
				$locales = $this->get_target_locales();
				$default = get_site_option('field_default_locale');
				$values  = get_post_meta( $post->ID, 'smartling_post_widget_data' );

				foreach ($locales as $index => $language): 

					if ($values[0]) {
						$value   = $values[0]['locale_' . $language];
						$checked = $value == 'true' ? 'checked="checked" disabled' : '';
					}

					$display = $default == $language ? 'style="display:none"' : 'style="display:block;" ';
					?>
					
					<p <?php echo $display ?>>
						<label>
							<input type="hidden" name="smartling_post_widget[locale_<?php echo $language; ?>]" value="<?php echo $value ?>">
							<input type="checkbox" <?php echo $checked; ?> name="locale_<?php echo $language; ?>">
							<span><?php echo $language; ?></span>
						</label>
						<?php if ($value == 'true'): ?>
							<span title="In Progress" class="widget-btn yellow"><span>24%</span></span>
						<?php endif ?>
					</p>

				<?php endforeach;
			?>
		</div>
		<div class="bottom">
			
			<?php submit_button('Send to Smartling'); ?>
<!-- 			<div class="submit">
				<a href="#" id="submit" class="button button-primary button-large">Send</a>
				<input name="save" type="submit" class="button button-primary button-large" id="publish" accesskey="p" value="Send 2">
			</div> -->
		</div>

	</div>
</div>






