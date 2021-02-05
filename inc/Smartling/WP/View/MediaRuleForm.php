<?php
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Tuner\MediaAttachmentRulesManager;

?>
<style>
    th.row {
        display: inherit !important;
    }
</style>
<div class="wrap">
    <h2><?= get_admin_page_title(); ?></h2>
    <?php
    $data = $this->getViewData();
    /**
     * @var MediaAttachmentRulesManager $manager
     */
    $manager = $data['manager'];

    $id = '';
    $block = '';
    $path = '';

    if (array_key_exists('id', $_GET)) {
        $id = $_GET['id'];
    }

    if ('' !== $id) {
        $manager->loadData();
        if (isset($manager[$id])) {
            $block = $manager[$id]['block'];
            $path = $manager[$id]['path'];
        }
    }
    ?>

    <form id="smartling_customization_tuning_media_form" action="<?= get_admin_url(null, '/admin-post.php')?>" method="POST">
        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'smartling_customization_tuning_media_form_save',
        ]); ?>

        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'media[id]',
            'value' => $id,
        ]); ?>

        <h3><?= __('Media rule') ?></h3>
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
                        'placeholder' => __('JSON path to media id'),
                        'data-msg' => __('Please specify JSON path to media id'),
                        'required' => 'required',
                        'value' => htmlentities($path),
                    ])?>
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
