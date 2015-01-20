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
        <h3><?php echo __( 'Account Info', $this->getPluginInfo()->getDomain()) ?></h3>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><?php echo __( 'API Url', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <input type="text" name="smartling_settings[apiUrl]" value="<?php echo $this->getPluginInfo()->getOptions()->getAccountInfo()->getApiUrl(); ?>">
                    <br>
                    <small><?php echo __( 'Set api url. Default', $this->getPluginInfo()->getDomain()) ?>: https://api.smartling.com/v1</small>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __( 'Project ID', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <input type="text" name="smartling_settings[projectId]" value="<?php echo $this->getPluginInfo()->getOptions()->getAccountInfo()->getProjectId(); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __( 'Key', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <?php $key = $this->getPluginInfo()->getOptions()->getAccountInfo()->getKey(); ?>
                    <input type="text" id="api_key" name="apiKey" value="">
                    <input type="hidden" name="smartling_settings[apiKey]" value="<?php echo $key ?>">
                    <br>
                    <?php if ($key) { ?>
                        <small><?php echo __( 'Current Key', $this->getPluginInfo()->getDomain()) ?>: <?php echo substr($key,0,-4) . '****' ?></small>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __( 'Retrieval Type', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <?php
                    $option  = $this->getPluginInfo()->getOptions()->getAccountInfo()->getRetrievalType();
                    $buttons = $this->getPluginInfo()->getOptions()->getRetrievalTypes();
                    $checked = $option ? $option : $buttons[1];
                    foreach ($buttons as $button) {
                        ?>
                        <label class="radio-label">
                            <p>
                                <input type="radio" <?php echo $button == $checked ? 'checked="checked"' : ''; ?> name="smartling_settings[retrievalType]" value="<?php echo $button; ?>">
                                <?php echo __($button, $this->getPluginInfo()->getDomain());  ?>
                            </p>
                        </label>
                        <br>

                    <?php } ?>

                    <small><?php echo __( 'Param for download translate', $this->getPluginInfo()->getDomain()) ?>.</small>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __( 'Target Locales', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <?php
                    $locales = $this->getSiteLocales();
                    $default = $this->getPluginInfo()->getOptions()->getLocales()->getDefaultLocale();
                    $targetLocales = $this->getPluginInfo()->getOptions()->getLocales()->getTargetLocales();
                    foreach ($locales as $value) {
                        if($default == $value) {
                            continue;
                        }
                        $short = null;
                        $checked = '';
                        foreach($targetLocales as $target) {
                            if($target->getLocale() == $value) {
                                $short = $target->getTarget();
                                $checked = $target->getEnabled() ? 'checked="checked"' : '';
                                break;
                            }
                        }

                        ?>

                        <div>
                            <p class="plugin-locales">
                                <label class="radio-label">
                                    <input type="checkbox" <?php echo $checked; ?> name="smartling_settings[targetLocales][<?php echo $value; ?>][enabled]">
                                    <span><?php echo $value; ?></span>
                                </label>
                                <input type="text" name="smartling_settings[targetLocales][<?php echo $value; ?>][target]" value="<?php echo $short; ?>">
                            </p>
                        </div>

                    <?php } ?>


                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __( 'Default Locale', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <?php $default =  $this->getPluginInfo()->getOptions()->getLocales()->getDefaultLocale(); ?>

                    <p><?php echo __( 'Site default language is', $this->getPluginInfo()->getDomain()) ?>: <?php echo $default; ?></p>
                    <p>
                        <a href="#" id="change-default-locale"><?php echo __( 'Change default locale', $this->getPluginInfo()->getDomain()) ?></a>
                    </p>
                    <br>
                    <?php $locales = $this->getSiteLocales(); ?>
                    <select name="smartling_settings[defaultLocale]" id="default-locales">
                        <?php foreach ($locales as $key => $value) {
                            $checked = $default == $value ? 'selected' : '';
                            ?>
                            <option value="<?php echo $value; ?>" <?php echo $checked; ?>> <?php echo $value; ?> </option>

                        <?php } ?>
                    </select>
                </td>
            </tr>
            <tr style="display: none;">
                <th scope="row"><?php echo __( 'Callback URL', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <label class="radio-label">
                        <p>
                            <?php
                            $option = $this->getPluginInfo()->getOptions()->getAccountInfo()->getCallBackUrl();
                            $checked = $option == true ? 'checked="checked"' : '';
                            ?>
                            <input type="checkbox" name="smartling_settings[callbackUrl]" <?php echo $checked; ?> >
                            <?php echo __( 'Use smartling callback', $this->getPluginInfo()->getDomain()) ?>: /smartling/callback/%cron_key
                        </p>
                    </label>
                    <br>

                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo __( 'Auto authorize', $this->getPluginInfo()->getDomain()) ?></th>
                <td>
                    <label class="radio-label">
                        <p>
                            <?php
                            $option =  $this->getPluginInfo()->getOptions()->getAccountInfo()->getAutoAuthorize();
                            $checked = $option == true ? 'checked="checked"' : '';
                            ?>
                            <input type="checkbox" name="smartling_settings[autoAuthorize]" <?php echo $checked; ?> >
                            <?php echo __( 'Auto authorize content', $this->getPluginInfo()->getDomain()) ?>
                        </p>
                    </label>
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
</div>