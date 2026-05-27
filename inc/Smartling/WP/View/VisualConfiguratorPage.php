<?php
/**
 * @var array $viewData
 * @var array $replacerOptions
 */
$replacerOptions = $viewData['replacerOptions'] ?? [];
?>
<div class="wrap">
    <h1><?= esc_html__('Smartling Visual Configurator') ?></h1>
    <p>
        <?= esc_html__('Browse content and assign translation rules (copy / translate / reference) to JSON fields and meta keys.') ?>
    </p>
    <div id="smartling-visual-configurator-root"
         data-replacer-options="<?= esc_attr(json_encode($replacerOptions)) ?>"></div>
    <noscript>
        <p><?= esc_html__('This page requires JavaScript to be enabled in your browser.') ?></p>
    </noscript>
</div>
