<?php
use Smartling\Helpers\StringHelper;
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

        <h3><?= $this->getWidgetHeader(); ?></h3>
        <?= WPAbstract::checkUncheckBlock(); ?>
        <?php
        $nameKey = PostBasedWidgetControllerStd::WIDGET_DATA_NAME;
        ?>
        <div class="locale-list">
        <?php

        \Smartling\Helpers\ArrayHelper::sortLocales($locales);

        foreach ($locales as $locale) {
            $value = false;

            $status = '';
            $submission = null;
            $statusValue = null;
            $id = null;
            if (null !== $data['submissions']) {
                foreach ($data['submissions'] as $item) {
                    /**
                     * @var SubmissionEntity $item
                     */
                    if ($item->getTargetBlogId() === $locale->getBlogId()) {
                        $value = true;
                        $statusValue = $item->getStatus();
                        $id = $item->getId();
                        $percent = $item->getCompletionPercentage();
                        $status = $item->getStatusColor();
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
                        $value
                    ); ?>
                </div>
                <div class="smtPostWidget-progress">
                    <?php if ($value) { ?>
                        <?= WPAbstract::localeSelectionTranslationStatusBlock(
                            __($statusValue),
                            $status,
                            $percent
                        ); ?>
                        <?= WPAbstract::inputHidden(
                            $id
                        ); ?>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        </div>

    </div>
    <div class="smtPostWidget-submitBlock">
        <?= WPAbstract::submitBlock(true); ?>
    </div>
    </div><?php
} else {
    ?>
    <div id="smartling-post-widget">
        <div class="fields">
            No suitable target locales found.<br/>
            Please check your <a
                href="/wp-admin/network/admin.php?page=smartling_configuration_profile_setup&action=edit&profile=<?= $data['profile']->getId(); ?>">settings.</a>
        </div>
    </div>
<?php } ?>

