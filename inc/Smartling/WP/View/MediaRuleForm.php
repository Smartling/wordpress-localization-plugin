<?php
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\WP\Controller\MediaRuleForm;

?>
<style>
    th.row {
        display: inherit !important;
    }
</style>
<div class="wrap">
    <h2><?= get_admin_page_title() ?></h2>
    <?php
    /**
     * @var MediaRuleForm $this
     */
    $manager = $this->mediaAttachmentRulesManager;

    $id = '';
    $block = '';
    $path = '';
    $replacerId = '';

    if (array_key_exists('id', $_GET)) {
        $id = $_GET['id'];
    }

    if ('' !== $id) {
        $manager->loadData();
        if (isset($manager[$id])) {
            $block = $manager[$id]['block'];
            $path = $manager[$id]['path'];
            $replacerId = $manager[$id]['replacerId'];
        }
    }
    ?>

    <form id="<?= MediaRuleForm::SLUG?>" action="<?= admin_url('admin-post.php')?>" method="POST">
        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => MediaRuleForm::ACTION_SAVE,
        ]) ?>

        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'media[id]',
            'value' => $id,
        ]) ?>

        <h3><?= __('Gutenberg block rule') ?></h3>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="block">
                        <?= __('Block name')?>
                    </label>
                </th>
                <td>
                    <?= HtmlTagGeneratorHelper::tag('input', '', [
                        'type' => 'text',
                        'id' => 'block',
                        'name' => 'media[block]',
                        'placeholder' => __('Name of custom block'),
                        'data-msg' => __('Please specify custom block name'),
                        'required' => 'required',
                        'value' => htmlentities($block),
                    ])?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="path">
                        <?= __('JSON path')?>
                    </label>
                </th>
                <td>
                    <?= HtmlTagGeneratorHelper::tag('input', '', [
                        'type' => 'text',
                        'id' => 'path',
                        'name' => 'media[path]',
                        'placeholder' => __('Attribute JSON path'),
                        'data-msg' => __('Please specify JSON path'),
                        'required' => 'required',
                        'value' => htmlentities($path),
                    ])?>
                    <br /><?= __('Treated as regex, if JSONpath is required (e. g. nested attributes), start with ')?> <pre style="display: inline; border: 1px solid #f00">$.</pre><br/>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="replacerType">
                        <?= __('Processor type')?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $replacerId,
                            $this->replacerFactory->getListForUi(),
                        ),
                        [
                            'id' => 'replacerType',
                            'name' => 'media[replacerType]',
                        ],
                    )
                    ?>
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
