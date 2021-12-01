<?php

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Models\TestRunViewData;
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
if ($viewData->getTestBlogId() === null) {
?>
<form id="testRunForm">
    <input type="hidden" id="sourceBlogId" name="sourceBlogId" value="<?= get_current_blog_id()?>">
    <table class="form-table" style="width: 50%">
        <tr>
            <th><label for="taxonomy">Target blog</label></th>
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
        button.prop('disable', true);
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
        button.prop('disable', false);
    });
</script>
<?php
} else {
    if ($viewData->getUploadCronLastFinishTime() < time() - $viewData->getUploadCronIntervalSeconds() * 2) {
        echo '<h2>Warning: last time upload job finished was ' . ($viewData->getUploadCronLastFinishTime() - time()) . 'seconds ago. Please verify cron jobs are set up properly in your WordPress installation</h2>';
    }
?>
<table>
    <tr>
        <th>Status</th>
        <th>Count</th>
    </tr>
    <tr>
        <td>New</td>
        <td><?= $viewData->getNew()?></td>
    </tr>
    <tr>
        <td>In progress</td>
        <td><?= $viewData->getInProgress()?></td>
    </tr>
    <tr>
        <td>Completed</td>
        <td><?= $viewData->getCompleted()?></td>
    </tr>
    <tr>
        <td>Failed</td>
        <td><?= $viewData->getFailed()?></td>
    </tr>
</table>
<?php
if ($viewData->getNew() === 0) {
?>
<p>Test run is in progress.</p>
<?php
}
}
?>
