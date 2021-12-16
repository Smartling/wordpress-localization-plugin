<?php

use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\WP\Controller\TestRunController;

/**
 * @var TestRunController $this
 */
$blogs = $this->getViewData()['blogs'];
?>
<h1>Test run</h1> <!--needed for admin notices-->
<p>Send all posts and pages and their related content one level deep for pseudo translation, and check the result for known issues. After a test run completes you should check it too, to see any issues that were not detected. Expected result after a test run is that all text and media content gets translated.</p>
<form id="testRunForm">
    <input type="hidden" id="sourceBlogId" name="sourceBlogId" value="<?= get_current_blog_id()?>">
    <table class="form-table" style="width: 50%">
        <tr>
            <th><label for="taxonomy">Target blog</label></th>
            <td><?= HtmlTagGeneratorHelper::tag(
                    'select',
                    HtmlTagGeneratorHelper::renderSelectOptions(null, $blogs),
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
