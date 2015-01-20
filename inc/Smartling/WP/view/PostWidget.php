<div id="smartling-post-widget">

    <div class="fields">
        <h3>Translate this post into:</h3>
        <?php

        $locales = $this->getPluginInfo()->getOptions()->getLocales()->getTargetLocales();
        $values  = get_post_meta($data->ID, 'smartling_post_widget_data');

        foreach ($locales as $index => $locale) {
            $value = false;
            $checked = '';
            if ($values && $values[0]) {
                $value   = $values[0]['locale_' . $locale->getLocale()];
                $checked = $value == 'true' ? 'checked="checked"' : '';
            }
            ?>

            <p>
                <label>
                    <input type="hidden" name="smartling_post_widget[locale_<?php echo $locale->getLocale(); ?>]" value="<?php echo $value; ?>">
                    <input type="checkbox" <?php echo $checked; ?> name="locale_<?php echo $locale->getLocale(); ?>">
                    <span><?php echo $locale->getLocale(); ?></span>
                </label>
                <?php if ($value == 'true') { ?>
                    <span title="In Progress" class="widget-btn yellow"><span>24%</span></span>
                <?php } ?>
            </p>

        <?php } ?>
    </div>
    <div class="bottom">
        <input type="submit" value="Send to Smartling" class="button button-primary" id="submit" name="submit">
        <input type="submit" value="Download" class="button button-primary" id="submit" name="submit">
    </div>
</div>

