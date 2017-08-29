<?php

use Smartling\Helpers\UiMessageHelper;
use Smartling\WP\Table\SubmissionTableWidget;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>
<?php if (!\Smartling\Helpers\DiagnosticsHelper::isBlocked()) : ?>
    <div class="wrap">

        <?php UiMessageHelper::displayMessages(); ?>

        <h2><?= get_admin_page_title(); ?></h2>

        <?php

        /**
         * @var SubmissionTableWidget $submissionsTable
         */
        $submissionsTable = $data;

        ?>
        <div id="icon-users" class="icon32"><br/></div>

        <style>
            td.bulkActionCb {
                padding-left: 18px;
            }
        </style>

        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->

        <table width="100%">
            <tr>
                <td style="text-align: left;">
                    <p>
                    <form id="submissions-filter" method="get">
                        <input type="hidden" name="page" value="<?= $_REQUEST['page']; ?>"/>
                        <?= $submissionsTable->contentTypeSelectRender(); ?>
                        <?= $submissionsTable->statusSelectRender(); ?>
                        <?= $submissionsTable->stateSelectRender(); ?>
                        <?= $submissionsTable->targetLocaleSelectRender(); ?>
                        <?= $submissionsTable->renderSearchBox(); ?>
                        <?= $submissionsTable->renderSubmitButton(__('Apply Filter')); ?>
                    </form>
                    </p>
                </td>
            </tr>
        </table>
        <form id="submissions-main" method="post">
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
            <input type="hidden" name="page" value="<?= $_REQUEST['page']; ?>"/>
            <!-- Now we can render the completed list table -->
            <?php $submissionsTable->display() ?>
        </form>
    </div>
<?php else: ?>
    <div class="wrap">
        <h2>Smartling connector plugin is temporarily turned off.</h2>
    </div>
<?php endif; ?>
