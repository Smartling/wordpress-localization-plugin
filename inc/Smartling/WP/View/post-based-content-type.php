<?php

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\PostBasedWidgetControllerStd;
use Smartling\WP\WPAbstract;

/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();

/**
 * @var TargetLocale[] $locales
 */
$locales = $data['profile']->getTargetLocales();

$filteredLocales = [];

foreach ($locales as $locale) {
    /**
     * @var TargetLocale $locale
     */
    if (!$locale->isEnabled() || StringHelper::isNullOrEmpty($locale->getLabel())) {
        continue;
    }

    $filteredLocales[] = $locale;

}

$locales = $filteredLocales;
?>

<?php
if (!empty($locales)) {
    ?>
    <div id="smartling-post-widget">
    <div class="fields">

        <h3><?= $this->getWidgetHeader() ?></h3>
        <?= WPAbstract::checkUncheckBlock() ?>
        <?php
        $nameKey = PostBasedWidgetControllerStd::WIDGET_DATA_NAME;
        ?>
        <div class="locale-list">
            <?php

            ArrayHelper::sortLocales($locales);

            foreach ($locales as $locale) {
                $lastError = '';
                $value = false;
                $editUrl = '';
                $status = '';
                $submission = null;
                $statusValue = null;
                $id = null;
                $enabled = false;
                $statusFlags = [];
                $submissionId = null;
                if (null !== $data['submissions']) {

                    do_action(
                        ExportedAPI::ACTION_SMARTLING_PLACE_RECORD_ID,
                        ArrayHelper::first($data['submissions'])
                    );

                    foreach ($data['submissions'] as $item) {

                        /**
                         * @var SubmissionEntity $item
                         */
                        if (SubmissionEntity::SUBMISSION_STATUS_CANCELLED !== $item->getStatus() &&
                            $item->getTargetBlogId() === $locale->getBlogId()) {
                            $lastError = $item->getLastError();
                            $value = true;
                            $statusValue = $item->getStatus();
                            $id = $item->getId();
                            $percent = 1 === $item->getIsCloned() ? 100 : $item->getCompletionPercentage();
                            $status = $item->getStatusColor();
                            $statusFlags = $item->getStatusFlags();
                            $enabled = !(1 === $item->getIsCloned() || 1 === $item->hasLocks());

                            if (0 !== (int) $item->getTargetId()) {
                                $editUrl = WordpressContentTypeHelper::getEditUrl($item);
                            }
                            
                            /**
                             * Disable download checkbox for new state
                             */
                            $enabled = $enabled && $item->getStatus() !== SubmissionEntity::SUBMISSION_STATUS_NEW;

                            $submissionId = $item->getId();
                            break;
                        }
                    }
                }
                ?>
                <div class="smtPostWidget-rowWrapper">
                    <div class="smtPostWidget-row">
                        <?= WPAbstract::localeSelectionCheckboxBlock(
                            $nameKey,
                            $locale->getBlogId(),
                            $locale->getLabel(),
                            (false === $enabled ? false : $value),
                            $enabled,
                            $editUrl,
                            [],
                            $submissionId
                        ) ?>
                    </div>
                    <div class="smtPostWidget-progress">

                        <?php if ($value) { ?>
                            <?= WPAbstract::localeSelectionTranslationStatusBlock(
                                    __($statusValue),
                                    $status,
                                    $percent,
                                    $lastError,
                                    $statusFlags
                            ) ?>
                            <?= WPAbstract::inputHidden($id) ?>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

        </div>

    </div>
    <div class="smtPostWidget-submitBlock">
        <?= WPAbstract::submitBlock() ?>
    </div>
    </div><?php
} else {
    ?>
    <div id="smartling-post-widget">
        <div class="fields">
            No suitable target locales found.<br/>
            Please check your <a
                    href="<?= get_site_url() ?>/wp-admin/network/admin.php?page=smartling_configuration_profile_setup&action=edit&profile=<?= $data['profile']->getId() ?>">settings.</a>
        </div>
    </div>
<?php } ?>

