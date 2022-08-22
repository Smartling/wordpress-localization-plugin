<?php

use Smartling\Settings\TargetLocale;
use Smartling\WP\Table\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
$widgetName = 'bulk-submit-locales';

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
        <?php $bulkSubmitTable->display() ?>
        <div id="error-messages" class="tab"></div>
        <div class="postbox-container">
            <div id="panel-box" class="postbox hndle"><h2><span>Content actions</span></h2>
                <div class="inside">
                    <div id="action-tabs">
                        <span class="active" data-action="translate">Translate</span>
                        <span data-action="clone">Clone</span>
                    </div>
                    <div class="tab-panel">
                        <div id="translate" class="tab">
                            <?php
                            // Render job wizard.
                            $this->setViewData(
                                [
                                    'profile'     => $bulkSubmitTable->getProfile(),
                                    'contentType' => '',
                                ]
                            );
                            $this->renderViewScript('ContentEditJob.php');
                            ?>
                        </div>
                        <div id="clone" class="tab hidden">
                            <table>
                                <tr>
                                    <td>
                                        <h3><?= __('Clone into next languages:'); ?></h3>
                                        <div>
                                            <?= WPAbstract::checkUncheckBlock($widgetName) ?>
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
                                                    $widgetName,
                                                    $locale->getBlogId(),
                                                    $locale->getLabel(),
                                                    false
                                                ); ?>
                                            </p>
                                        <?php } ?>
                                    </td>
                                </tr>
                            </table>
                            <div class="clone-button">
                                <?= WPAbstract::bulkSubmitCloneButton(); ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="content-type" id="ct" value=""/>
            <input type="hidden" name="page" value="<?= $_REQUEST['page']; ?>"/>
            <input type="hidden" id="action" name="action" value="clone"/>
    </form>

    <script>
        (function ($) {
            $(document).ready(function () {
                $('div#action-tabs span').on('click', function () {
                    var $selector = $(this).attr('data-action');
                    $('div#action-tabs span').removeClass('active');
                    $(this).addClass('active');
                    $('div.tab').addClass('hidden');
                    $('#' + $selector).removeClass('hidden');
                });
            });
        })(jQuery);
    </script>

    </form>
</div>