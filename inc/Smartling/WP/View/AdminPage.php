<style>
    span.nonmanaged {
        color: darkgrey;
    }
</style>
<div class="wrap">

    <h2><?= get_admin_page_title(); ?></h2>
    <h3>Custom Shortcodes</h3>
    <?php
    $shortcodeTable = $this->getViewData()['shortcodes'];
    $filterTable = $this->getViewData()['filters'];
    /**
     * @var \Smartling\Ui\Table\ShortcodeTableClass $shortcodeTable
     */
    $shortcodeTable->prepare_items();
    /**
     * @var \Smartling\Ui\Table\LocalizationRulesTableWidget $filterTable
     */
    $filterTable->prepare_items();
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

</div>
