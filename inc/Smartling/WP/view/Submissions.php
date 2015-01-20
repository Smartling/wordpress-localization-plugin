<div class="wrap">
    <h2><?php echo get_admin_page_title() ?></h2>
    <div class="display-errors"></div>
    <?php
        $submissionsTable = $data;
        $submissionsTable->prepare_items();
    ?>
    <div id="icon-users" class="icon32"><br/></div>

    <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
    <form id="submissions-filter" method="get">
        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
        <!-- Now we can render the completed list table -->
        <?php $submissionsTable->display() ?>
    </form>
</div>