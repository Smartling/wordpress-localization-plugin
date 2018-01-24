<?php

use Smartling\Settings\TargetLocale;
use Smartling\WP\View\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();

?>
<div class="wrap">
    <style>
        table.form-table th {
            display: inline-table;
        }

        td.bulkActionCb {
            padding-left: 18px;
        }
    </style>
    <h2><?= get_admin_page_title(); ?></h2>

    <div class="display-errors"></div>
    <?php

    $bulkSubmitTable = $data;
    /**
     * @var BulkSubmitTableWidget $submissionsTable
     */
    $bulkSubmitTable->prepare_items();
    ?>


    <table class="form-table">
        <tr>
            <td>
                <form id="bulk-submit-type-filter" method="get">
                    <input type="hidden" name="page" value="<?= $_REQUEST['page']; ?>"/>
                    <?= $bulkSubmitTable->contentTypeSelectRender(); ?>
                    <?= $bulkSubmitTable->titleFilterRender(); ?>
                    <?= $bulkSubmitTable->renderSubmitButton(__('Apply Filter')); ?>
                </form>
            </td>
        </tr>
    </table>

    <form class="form-table" id="bulk-submit-main" method="post">
        <table>
            <tr>
                <td>
                    <h3><?= __('Translate into:'); ?></h3>
                    <div>
                        <?= WPAbstract::checkUncheckBlock(); ?>
                    </div>
                    <?php
                    /**
                     * @var BulkSubmitTableWidget $data
                     */

                    $locales = $data->getProfile()
                        ->getTargetLocales();

                    \Smartling\Helpers\ArrayHelper::sortLocales($locales);

                    foreach ($locales as $locale) {
                        /**
                         * @var TargetLocale $locale
                         */
                        if (!$locale->isEnabled()) {
                            continue;
                        }
                        ?>
                        <p>
                            <?= WPAbstract::localeSelectionCheckboxBlock(
                                'bulk-submit-locales',
                                $locale->getBlogId(),
                                $locale->getLabel(),
                                false
                            ); ?>
                        </p>
                    <?php } ?>
                </td>
            </tr>
        </table>
        <input type="hidden" name="content-type" id="ct" value=""/>
        <input type="hidden" name="page" value="<?= $_REQUEST['page']; ?>"/>
        <input type="hidden" id="action" name="action" value="clone"/>
        <?php $bulkSubmitTable->display() ?>
        <div id="error-messages"></div>
        &nbsp;
        <?= WPAbstract::bulkSubmitCloneButton(); ?>
    </form>

</div>