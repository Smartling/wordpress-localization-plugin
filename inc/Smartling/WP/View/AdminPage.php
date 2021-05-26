<?php

use Smartling\WP\Table\LocalizationRulesTableWidget;
use Smartling\WP\Table\MediaAttachmentTableWidget;
use Smartling\WP\Table\ShortcodeTableClass;

?>
<style>
    span.nonmanaged {
        color: darkgrey;
    }
</style>
<div class="wrap">

    <h2><?= get_admin_page_title(); ?></h2>
    <h3>Custom Shortcodes</h3>
    <?php
    /**
     * @var ShortcodeTableClass $shortcodeTable
     */
    $shortcodeTable = $this->getViewData()['shortcodes'];
    /**
     * @var LocalizationRulesTableWidget $filterTable
     */
    $filterTable = $this->getViewData()['filters'];
    /**
     * @var MediaAttachmentTableWidget $mediaTable
     */
    $mediaTable = $this->getViewData()['media'];

    foreach ([$shortcodeTable, $filterTable, $mediaTable] as $widget) {
        if (!$widget instanceof WP_List_Table) {
            throw new \RuntimeException('Widgets expected to be children of WP_List_Table');
        }
        $widget->prepare_items();
    }
    ?>
    <div id="icon-users" class="icon32"><br/></div>

    <form id="shortcodes-table-list" method="get">
        <?= $shortcodeTable->renderNewProfileButton(); ?>
        <input type="hidden" name="page" value="smartling_customization_tuning_shortcode_form"/>
        <input type="hidden" name="id" value=""/>
        <?php $shortcodeTable->display(); ?>
    </form>

    <h3>Custom Filters</h3>

    <form id="filters-table-list" method="get">
        <?= $filterTable->renderNewFilterButton(); ?>
        <input type="hidden" name="page" value="smartling_customization_tuning_filter_form"/>
        <input type="hidden" name="id" value=""/>
        <?php $filterTable->display(); ?>
    </form>

    <h3>Gutenberg block rules</h3>

    <form id="media-rules-table-list" method="get">
        <?= $mediaTable->renderNewButton()?>
        <input type="hidden" name="page" value="smartling_customization_tuning_media_form"/>
        <input type="hidden" name="id"/>
        <?php $mediaTable->display()?>
    </form>
</div>
