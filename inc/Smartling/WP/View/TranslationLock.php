<?php defined('IFRAME_REQUEST') || define('IFRAME_REQUEST', true); ?>


<?php
define('WP_ADMIN', true);
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
do_action('admin_init');
set_current_screen();
$title = __('Add Plugins');
$parent_file = 'admin.php';
global $menu;
$menu = [];
//require_once __DIR__ . '/iframe-header.php';
 include(ABSPATH . 'wp-admin/admin-header.php');
?>
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

<?= $table->display(); ?>


<?php
//wp_print_admin_notice_templates();

include(ABSPATH . 'wp-admin/admin-footer.php'); ?>