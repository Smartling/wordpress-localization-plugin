<style>
    th.row {
        display: inherit !important;
    }
</style>
<div class="wrap">

    <h2><?= get_admin_page_title()?></h2>
    <?php

    use Smartling\Helpers\HtmlTagGeneratorHelper;
    use Smartling\Tuner\FilterManager;
    use Smartling\WP\Controller\FilterForm;
    /**
     * @var FilterForm $this
     */
    $data = $this->viewData;

    /**
     * @var FilterManager $manager
     */
    $manager = $data['manager'];

    $id = '';
    $shortcode = '';
    $action = 'copy';
    $type = 'post';

    if (array_key_exists('id', $_GET)) {
        $id = $_GET['id'];
    }

    if ('' !== $id) {
        $manager->loadData();

        if (isset($manager[$id])) {
            $record = $manager[$id];
            $pattern = $record['pattern'];
            $action = $record['action'];
            $type = $record['type'];
        }
    }
    ?>

    <form id="<?= FilterForm::SLUG?>"
          action="<?= admin_url('admin-post.php')?>" method="POST">
        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'action',
            'value' => FilterForm::ACTION_SAVE,
        ])?>

        <?= HtmlTagGeneratorHelper::tag('input', '', [
            'type'  => 'hidden',
            'name'  => 'filter[id]',
            'value' => $id,
        ])?>


        <h3><?= __('Filter details') ?></h3>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="filterPattern">
                        <?= __('Filter Pattern')?>
                    </label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag('input', '', [
                        'type'        => 'text',
                        'id'          => 'filterPattern',
                        'name'        => 'filter[pattern]',
                        'placeholder' => __('Write here the pattern'),
                        'data-msg'    => __('Please write the pattern'),
                        'required'    => 'required',
                        'value'       => htmlentities($pattern ?? ''),
                    ])
                    ?>
                    <br>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="action">Action</label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $action,
                            [
                                'skip' => __('Skip. Do not process.'),
                                'copy' => __('Copy. Do not translate'),
                                'localize' => __('That is a reference to another content'),
                            ]
                        ),
                        [
                            'id'   => 'action',
                            'name' => 'filter[action]',
                        ]
                    )?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="type">Type of related content</label>
                </th>
                <td>
                    <?=
                    HtmlTagGeneratorHelper::tag(
                        'select',
                        HtmlTagGeneratorHelper::renderSelectOptions(
                            $type,
                            [
                                'post' => __('Post, Page or Custom post type'),
                                'media' => __('Image, Video or uploaded file'),
                                'taxonomy' => __('Category, Tag or another taxonomy'),
                            ]
                        ),
                        [
                            'id'   => 'type',
                            'name' => 'filter[type]',
                        ]
                    )?>
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
