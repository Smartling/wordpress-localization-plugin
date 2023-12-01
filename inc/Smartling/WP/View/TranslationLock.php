<?php
defined('IFRAME_REQUEST') || define('IFRAME_REQUEST', true);
defined('WP_ADMIN') || define('WP_ADMIN', true);
require_once(ABSPATH . '/wp-load.php');
nocache_headers();
require_once(ABSPATH . 'wp-admin/includes/admin.php');
set_screen_options();
$date_format = __('F j, Y');
$time_format = __('g:i a');
wp_enqueue_script('common');
global $pagenow, $wp_importers, $hook_suffix, $plugin_page, $typenow, $taxnow;
$page_hook = null;
$editing = false;
set_current_screen();
$title = __('Add Plugins');
$parent_file = 'admin.php';
if ($hook_suffix === null) {
    $hook_suffix = '';
}
global $menu;
$menu = [];
include(ABSPATH . 'wp-admin/admin-header.php');
?>
    <style>
        #adminmenumain {
            display: none;
        }

        #wpcontent {
            margin: 0 !important;
        }

        div.error, div.update-nag, div#screen-meta-links {
            display: none;
        }

        th#locked {
            max-width: 50px;
        }

        th#locked {
            max-width: 70px !important;
            width: 70px;
        }

        }
    </style>
    <div class="wrap">
    <h1 class="wp-heading-inline"><?= esc_html($title); ?></h1>
    <hr class="wp-header-end">
    <?php
    /**
     * @var WPAbstract $this
     * @var WPAbstract self
     */
    $data = $this->getViewData();
    /**
     * @var \Smartling\WP\Table\TranslationLockTableWidget $table
     */
    $table = $data['table'];
    /**
     * @var \Smartling\Submissions\SubmissionEntity $submission
     */
    $submission = $data['submission'];
    ?>

    <form method="post">
        <div>
            <?=
            \Smartling\Helpers\HtmlTagGeneratorHelper::tag(
                'label',
                __('Lock all fields'),
                [
                    'for' => 'locked_page',
                ]
            );
            ?>
            <?php
            $options = [
                'id'   => 'locked_page',
                'type' => 'checkbox',
                'name' => 'lock_page',
            ];

            if (1 === $submission->getIsLocked()) {
                $options['checked'] = 'checked';
            }
            ?>

            <?=
            \Smartling\Helpers\HtmlTagGeneratorHelper::tag('input', '', $options);
            ?>
        </div>

        <?= $table->display(); ?>
        <?= \Smartling\Helpers\HtmlTagGeneratorHelper::tag(
            'input',
            '',
            [
                'id'    => 'submit',
                'type'  => 'submit',
                'value' => 'Save',
                'title' => __('Save settings'),
                'class' => 'button button-primary',
                'name'  => 'submit',
            ]); ?>
    </form>
    <script>
        (function ($) {

            var setStates = function (e) {
                if ($('input[name=lock_page]:checked').length) {
                    $('.field_lock_element').each(function (i, v) {
                        $(v).attr('disabled', 'disabled');
                    });
                } else {
                    $('.field_lock_element').each(function (i, v) {
                        $(v).removeAttr('disabled');
                    });
                }
            };


            $('#locked_page').on('click', setStates);

            $('#submit').on('click', function (e) {
                $('.field_lock_element').each(function (i, v) {
                    $(v).removeAttr('disabled');
                });
            });
            setStates();
        })(jQuery);


    </script>

<?php include(ABSPATH . 'wp-admin/admin-footer.php'); ?>