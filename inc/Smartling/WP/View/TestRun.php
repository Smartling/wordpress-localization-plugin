<?php

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Models\TestRunViewData;
use Smartling\WP\Controller\ConfigurationProfilesController;
use Smartling\WP\Controller\TestRunController;

/**
 * @var TestRunController $this
 * @var TestRunViewData $viewData
 */
$viewData = $this->getViewData();
?>
<h1>Test run</h1> <!--needed for admin notices-->
<p>Send all posts and pages and their related content one level deep for pseudo translation, and check the result for known issues. After a test run completes you should check it too, to see any issues that were not detected. Expected result after a test run is that all text and media content gets translated.</p>
<?php
if ($viewData->getTestBlogId() === null && count($viewData->getBlogs()) === 0) {
    echo '<p>You need to either <a href="' . get_admin_url(null, '/network/site-new.php') . '">add a new site</a> or completely remove submissions from an existing one to start a test run.</p>';
}
if ($viewData->getTestBlogId() === null) {
?>
<form id="testRunForm">
    <input type="hidden" id="sourceBlogId" name="sourceBlogId" value="<?= get_current_blog_id()?>">
    <table class="form-table" style="width: 50%">
        <tr>
            <th style="width: 400px"><label for="taxonomy">Target blog (blogs with existing submissions excluded)</label></th>
            <td><?= HtmlTagGeneratorHelper::tag(
                    'select',
                    HtmlTagGeneratorHelper::renderSelectOptions(null, $viewData->getBlogs()),
                    ['id' => 'targetBlogId', 'name' => 'targetBlogId']
                )?>
            </td>
        </tr>
    </table>
    <input type="button" class="button button-primary" id="testRun" onclick="return false" value="Start Test Run">
</form>
<script>
    const button = jQuery('#testRunForm #testRun');
    button.on('click', function () {
        button.prop('disabled', true);
        jQuery.post(ajaxurl + '?action=smartling_test_run', jQuery('#testRunForm').serialize(), function (data) {
            const success = data.success;
            if (success) {
                const message = 'Queued for test run';
                if (wp && wp.data && wp.data.dispatch) {
                    try {
                        wp.data.dispatch('core/notices').createSuccessNotice(message);
                    } catch (e) {
                        admin_notice(message, 'success')
                        console.log(e);
                    }
                } else {
                    admin_notice(message, 'success');
                }
                document.location.reload();
            } else {
                if (wp && wp.data && wp.data.dispatch) {
                    try {
                        wp.data.dispatch('core/notices').createErrorNotice(data.data);
                    } catch (e) {
                        admin_notice(message, 'error');
                        console.log(e);
                    }
                } else {
                    admin_notice(data.data, 'error');
                }
            }
        });
        button.prop('disabled', false);
    });
</script>
<?php
} else {
    if ($viewData->getUploadCronLastFinishTime() < time() - $viewData->getUploadCronIntervalSeconds() * 2) {
        echo '<h2>Warning: Please verify cron jobs are set up properly in your WordPress installation. If the cron jobs are not set up properly, automatic uploads and downloads will not work, you need to manually trigger the cron jobs from the <a href="' . get_admin_url(null, 'admin.php?page=' . ConfigurationProfilesController::MENU_SLUG) . '">Settings</a> </h2>';
    }
?>
<table class="smartling-border-table">
    <tr>
        <th>Submission Status</th>
        <th>Count</th>
    </tr>
    <tr>
        <td>Pending upload</td>
        <td class="numeric"><?= $viewData->getNew()?></td>
    </tr>
    <tr>
        <td>Pending download</td>
        <td class="numeric"><?= $viewData->getInProgress()?></td>
    </tr>
    <tr>
        <td>Completed</td>
        <td class="numeric"><?= $viewData->getCompleted()?></td>
    </tr>
    <tr>
        <td>Failed</td>
        <td class="numeric"><?= $viewData->getFailed()?></td>
    </tr>
</table>
<?php
if ($viewData->getNew() + $viewData->getInProgress() === 0) {
    echo '<p>Test run is has completed. You should now review the translated blog to check if English strings everywhere are replaced with pseudo translations.</p>';
} else {
    echo '<p>Test run is in progress. When there are no pending submissions, you should review the translated blog.';
}
}
