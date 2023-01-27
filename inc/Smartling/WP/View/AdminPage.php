<?php

use Smartling\WP\Controller\AdminPage;
use Smartling\WP\Controller\FilterForm;
use Smartling\WP\Controller\MediaRuleForm;
use Smartling\WP\Controller\ShortcodeForm;
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
        <?= $shortcodeTable->renderNewShortcodeButton()?>
        <input type="hidden" name="page" value="<?= ShortcodeForm::SLUG?>"/>
        <input type="hidden" name="id" value=""/>
        <?php $shortcodeTable->display(); ?>
    </form>

    <h3>Custom Filters</h3>

    <form id="filters-table-list" method="get">
        <?= $filterTable->renderNewFilterButton(); ?>
        <input type="hidden" name="page" value="<?= FilterForm::SLUG?>"/>
        <input type="hidden" name="id" value=""/>
        <?php $filterTable->display(); ?>
    </form>

    <h3>Gutenberg block rules</h3>
    <form id="media-rules-table-list" method="get">
        <?= $mediaTable->renderNewButton()?>
        <a class="button action" href="<?= admin_url('admin-post.php?action=' . AdminPage::ACTION_EXPORT_GUTENBERG_RULES)?>">
            <?= __('Export rules')?>
        </a>
        <input type="hidden" name="page" value="<?= MediaRuleForm::SLUG?>"/>
        <?php $mediaTable->display()?>
    </form>

    <h3>Import Gutenberg block rules</h3>
    <form id="import-media-rules" method="post" enctype="multipart/form-data" action="<?= admin_url('admin-post.php?action=' . AdminPage::ACTION_IMPORT_GUTENBERG_RULES)?>">
        <input type="file" name="file" id="importFileInput" accept=".txt"/>
        <input class="button" type="submit"/>
    </form>
</div>
