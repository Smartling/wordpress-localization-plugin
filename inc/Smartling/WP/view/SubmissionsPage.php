<div class="wrap">
    <h2><?= get_admin_page_title(); ?></h2>
    <div class="display-errors"></div>
    <?php
        use Smartling\WP\View\SubmissionTableWidget;

        $submissionsTable = $data;
        /**
         * @var SubmissionTableWidget $submissionsTable
         */
        $submissionsTable->prepare_items();
    ?>
    <div id="icon-users" class="icon32"><br/></div>

    <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
    <form id="submissions-filter" method="post">

    <p>
        <?= $submissionsTable->getStatusSelect()->render(); ?>
        <?= $submissionsTable->getTypeSelect()->render(); ?>
        <?= $submissionsTable->renderJSSubmitButtion('Apply Filter', 'submissions-filter'); ?>
    </p>


        <?php $submissionsTable->search_box( __( 'Search' ), 's' ); ?>

        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?= $_REQUEST['page']; ?>" />
        <!-- Now we can render the completed list table -->
        <?php $submissionsTable->display() ?>
    </form>
</div>