<?php

use Smartling\Helpers\ArrayHelper;
use Smartling\WP\Controller\BulkSubmitController;
use Smartling\WP\Table\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;

/**
 * @var BulkSubmitController $this
 */
$data = $this->viewData;
assert($data instanceof BulkSubmitTableWidget);
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
    $bulkSubmitTable->prepare_items();
    ?>

    <table class="form-table">
        <tr>
            <td>
                <form id="bulk-submit-type-filter" method="get">
                    <input type="hidden" name="page" value="<?= $_REQUEST['page']; ?>"/>
                    <?= $bulkSubmitTable->contentTypeSelectRender(); ?>
                    <?= $bulkSubmitTable->titleFilterRender(); ?>
                    <?= $bulkSubmitTable->submissionsStatusFilterRender()?>
                    <?= $bulkSubmitTable->renderSubmitButton(__('Apply Filter')); ?>
                </form>
            </td>
        </tr>
    </table>

    <form class="form-table" id="bulk-submit-main" method="post">
        <?php if ($bulkSubmitTable->isDataFiltered()) {?>
            <h3 style="float: right">Additional table filters present, later pages will have less rows</h3>
        <?php }?>
        <?php $bulkSubmitTable->display() ?>
        <div id="error-messages" class="tab"></div>
        <?php
        $locales = $data->getProfile()->getTargetLocales();
        ArrayHelper::sortLocales($locales);
        $localesData = array_map(function($locale) {
            return [
                'blogId' => $locale->getBlogId(),
                'label' => $locale->getLabel(),
                'smartlingLocale' => $locale->getSmartlingLocale(),
                'enabled' => $locale->isEnabled()
            ];
        }, array_filter($locales, fn($l) => $l->isEnabled()));
        ?>
        <div id="smartling-app"
             data-bulk-submit="true"
             data-content-type=""
             data-content-id="0"
             data-locales='<?= htmlspecialchars(json_encode(array_values($localesData), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>'
             data-ajax-url="<?= admin_url('admin-ajax.php') ?>"
             data-admin-url="<?= admin_url('admin-ajax.php') ?>"></div>
        <div class="postbox-container" style="display:none;">
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
                                        $locales = $data->getProfile()
                                            ->getTargetLocales();

                                        ArrayHelper::sortLocales($locales);

                                        foreach ($locales as $locale) {
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
</div>
