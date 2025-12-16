<style>
    table.form-table th {
        display: inherit !important;
    }
</style>
<div class="wrap">

    <h2><?= get_admin_page_title(); ?></h2>
    <?php

    use Smartling\Helpers\HtmlTagGeneratorHelper;
    use Smartling\Tuner\ShortcodeManager;
    use Smartling\WP\Controller\ShortcodeForm;

    /**
     * @var ShortcodeForm $this
     */
    $data = $this->viewData;

    /**
     * @var ShortcodeManager $manager
     */
    $manager = $data['manager'];

    $id        = '';
    $shortcode = '';

    if (array_key_exists('id', $_GET)) {
        $id = $_GET['id'];
    }

    if ('' !== $id) {
        $manager->loadData();

        if (isset($manager[$id])) {
            $record    = $manager[$id];
            $shortcode = $record['name'];
        }
    }
    ?>

    <form id="admin_post_<?= ShortcodeForm::SLUG?>>"
          action="<?= admin_url('admin-post.php')?>" method="POST">
        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'action',
            'value' => ShortcodeForm::ACTION_SAVE,
        ]); ?>

        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'shortcode[id]',
            'value' => $id,
        ]); ?>


        <h3><?= __('Shortcode details') ?></h3>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="shortcodeName">
                        <?= __('Shortcode'); ?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag('input', '', [
                        'type'        => 'text',
                        'id'          => 'shortcodeName',
                        'name'        => 'shortcode[name]',
                        'placeholder' => __('Write here shortcode'),
                        'data-msg'    => __('Please input the shortcode'),
                        'required'    => 'required',
                        'value'       => htmlentities($shortcode),
                    ])
                    ?>
                    <br>
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
