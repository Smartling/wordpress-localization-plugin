<?php
/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>

<style>
    ul.tabs {
        margin: 0px;
        padding: 0px;
        list-style: none;
    }

    ul.tabs li {
        background: none;
        color: #222;
        display: inline-block;
        padding: 10px 15px;
        margin-top: 0px;
        margin-bottom: 0px;
        cursor: pointer;
    }

    ul.tabs li.current {
        background: #ededed;
        font-weight: bold;
        color: #222;
    }

    .tab-content {
        display: none;
        background: #ededed;
        padding: 15px;
    }

    .tab-content.current {
        display: inherit;
    }

    #tab-create table {
        width: 100%;

    }

    #tab-create th {
        text-align: right;
        width: 150px;
        max-width: 150px;
    }

    #tab-create table td > * {
        min-width: 100%;
        max-width: 100%;
    }

    #tab-create table td > input[type=checkbox] {
        min-width: inherit;
        max-width: inherit;
    }

    th.center {
        width: 50%;
        text-align: center !important;
        margin-top: 6px;
    }


</style>
<div class="jobs-container">

    <ul class="tabs">
        <li class="tab-link current" data-tab="tab-create">Create New Job</li>
        <li class="tab-link" data-tab="tab-existing">Add to Existing Job</li>
    </ul>

    <div id="tab-create" class="tab-content current">
        <table>
            <tr>
                <th><label for="name">Name</label></th>
                <td><input id="name" type="text"/></td>
            </tr>
            <tr>
                <th><label for="description">Description</label</th>
                <td><textarea id="description" name="description"></textarea></td>
            </tr>
            <tr>
                <th><label for="dueDate">Due Date</label</th>
                <td><input type="text" id="dueDate" name="dueDate"/></td>
            </tr>
            <tr>
                <th><label for="cbAuthorize">Authorize Job</label</th>
                <td><input type="checkbox" id="cbAuthorize" name="cbAuthorize"/></td>
            </tr>
            <tr>
                <th>Target Locales</th>
                <td>
                    <div>
                        <?= \Smartling\WP\WPAbstract::checkUncheckBlock(); ?>
                    </div>
                    <?php
                    /**
                     * @var BulkSubmitTableWidget $data
                     */

                    $profile = $data['profile'];

                    $locales = $profile->getTargetLocales();

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
                            <?= \Smartling\WP\WPAbstract::localeSelectionCheckboxBlock(
                                'bulk-submit-locales',
                                $locale->getBlogId(),
                                $locale->getLabel(),
                                false
                            ); ?>
                        </p>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th class="center" colspan="2">
                    <button class="button button-primary" id="createJob">Create Job</button>
                </th>

            </tr>
        </table>

    </div>
    <div id="tab-existing" class="tab-content">
        <span id = "placeholder">Please wait...</span>
        <div class="hidden" id="jobsList">

            <table>
                <tr>
                    <th>
                        <lable for="jobSelect">Existing jobs</lable>
                    </th>
                    <td>
                        <select id="jobSelect">
                            <option value="none">-- pick up a job from the list --</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Due Date</th>
                    <td><span id="existingDueDate"></span></td>
                </tr>

                <tr>
                    <th>Target Locales</th>
                    <td><span id="existingTargetlocaleids"></span></td>
                </tr>

            </table>
        </div>
    </div>

</div>

<?php


?>
<script>
    (function ($) {
        $(document).ready(function () {

            $('#dueDate').datetimepicker({
                format: 'Y-m-d H:i:s',
                //inline: true,
                minDate: 0
            });

            $('ul.tabs li').click(function () {
                var tab_id = $(this).attr('data-tab');

                $('ul.tabs li').removeClass('current');
                $('.tab-content').removeClass('current');

                $(this).addClass('current');
                $("#" + tab_id).addClass('current');
            });


            $('#jobSelect').on('change', function(){


                var dueDate = $('option[value=' + $('#jobSelect').val() + ']').attr('dueDate');

                var targetlocaleids = $('option[value=' + $('#jobSelect').val() + ']').attr('targetlocaleids');

                $('#existingDueDate').html(dueDate);
                $('#existingTargetlocaleids').html(targetlocaleids);
            });


            $('#createJob').click(function(e){
                e.stopPropagation();
                e.preventDefault();
            });

            $('ul.tabs li').click(function () {


                var tab_id = $(this).attr('data-tab');

                if ('tab-existing' === tab_id) {


                    var data = {
                        'innerAction': 'list-jobs',
                        'params': {}
                    };

                    $.post("<?= admin_url('admin-ajax.php') ?>?action=smartling_job_api_proxy", data, function (response) {
                        response = JSON.parse(response);

                        $('#jobsList').removeClass('hidden');
                        $('#placeholder').addClass('hidden');
                        console.log(response);
                        if (200 == response.status) {

                            $('#jobSelect').html('');
                            //$('#jobsList').append('<lable for="jobSelect">Existing jobs</lable><select id="jobSelect"><option value="none">-- pick up a job from the list --</option></select>');

                            response.data.forEach(function (job) {
                                $option = '<option value="' + job.translationJobUid + '" dueDate="' + job.dueDate + '" targetLocaleIds="' + job.targetLocaleIds.join(',') + '">' + job.jobName + '</option>';

                                $('#jobSelect').append($option);
                            });

                        }

                        $('#jobSelect').change();

                    });

                }


            });


        })
    })(jQuery)
</script>
