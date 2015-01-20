<div id="smartling-post-widget">
    <div class="fields">
        <h3>Translate this post into:</h3>
        <?php

        $locales = $this->getPluginInfo()->getOptions()->getLocales()->getTargetLocales();

        foreach ($locales as $index => $locale) {
            $value = false;
            ?>
            <p>
                <label>
                    <input type="hidden" name="smartling_taxonomy_widget[locale_<?php echo $locale->getLocale(); ?>]" value="<?php echo $value; ?>">
                    <input type="checkbox" name="locale_<?php echo $locale->getLocale(); ?>">
                    <span><?php echo $locale->getLocale(); ?></span>
                </label>
            </p>
        <?php } ?>
    </div>
    <div class="bottom">
        <input type="submit" value="Send to Smartling" class="button button-primary" id="submit" name="submit">
        <input type="submit" value="Download" class="button button-primary" id="submit" name="submit">
    </div>
</div>