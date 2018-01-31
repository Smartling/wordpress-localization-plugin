<?php
/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>

<style>
    #tab-existing table {
        width: 100%;
    }

    #tab-existing th {
        text-align: right;
        width: 150px;
        max-width: 150px;
    }

    #tab-existing table td > * {
        min-width: 100%;
        max-width: 100%;
    }

    #tab-existing table td > input[type=checkbox] {
        min-width: inherit;
        max-width: inherit;
    }

    #placeholder {
        width: 100%;
        font-size: larger;
    }

    .job-wizard {
        margin-top: 25px;
    }

    .smartling_page_smartling-bulk-submit .job-wizard {
        margin-right: 17px;
    }

    div#job-tabs {
        width: 100%;
        max-width: 100%;
        height: 32px;
        min-height: 32px;
    }

    div#job-tabs span {
        display: inline-block;
        padding: 8px 5px 5px 5px;
        float: left;
        width: 120px;
        min-width: 120px;
        height: 22px;
        vertical-align: center;
        text-align: center;
        margin: 2px;
        border: 1px dotted black;
    }

    div#job-tabs span:hover, div#job-tabs span.active {
        background-color: lightgray;
        color: black;
        text-shadow: 1px 1px 1px grey;
    }

    div#job-tabs span:hover {
        cursor: pointer;
        background-color: #2A495F;
        color: whitesmoke;
    }
</style>
<div class="job-wizard">
    <div id="placeholder"><img
                src="data:image/gif;base64,R0lGODlhEAAQAMQfAPPmz+G8U+XFa8JrXdmkltabjerW0cFZStyqdrlFNuG5Yvz38tquou3WpufIf/rz6e3WuuC8rPfs4eXIvtWXduvSl+jMm+jMqd3At/LhwuG5gN+4V/DetL1MPejNif///yH/C05FVFNDQVBFMi4wAwEAAAAh+QQJAwAfACwAAAAAEAAQAAAFX+AnjmRZLsWQJIY5Qt1RQB63uFBSjE4QNCVJZ1dqBCqkwsH1gQQeowHRFYCMEh4PU1AhLhoCZqbSGWUCzE9qtAhkmDISwG0qdG5yn0AiKiSsJgtjXQcdgEwDHVJ4aWkhACH5BAkDAB8ALAAAAAAQABAAAAVW4CeOZFlKxZEkR2SKWNc6DuKaUVJ8iyMoAUtp0bmRAEFS6vW5BBajAob5CQCoJYCEdBEyF1sRkrocBS7M1tGK60DXgvi1kDCSJAhaqmNgSlQsBW9YTCEAIfkECQMAHwAsAAAAABAAEAAABVfgJ45kWS7FkSRHIZlf1B2IoCDDYWJJ9AQCRyDgKEk6BZMlACAtIrCPMGpaWBbUrNYkEWxFi4AnesCQAExToYM9Dz1CSyFhMEUGCIcDceiYqykrLW1fJSEAIfkECQMAHwAsAAAAABAAEAAABVPgJ45kWUrFkCRDsZjf1A2IICBHh5VTUgQBjweI6JQWkw/gJRoCYDDAE0qtWq9Yk8OSBQS4ooVFgimUIoiAQAgsJHYkTOeAEOI6BtgitRpEJFkmIQAh+QQJAwAfACwAAAAAEAAQAAAFTOAnjmRZLsWRJEckmZ/RHYggIEdnmIsXBB5fAJGImAIVUqUSOcCe0Kh0Sq1CX6VIBKkMYEeTBOK3FAwLJhnN4cB1JjBJYbAaFBbWUggAIfkECQMAHwAsAAAAABAAEAAABVTgJ45kWS7EkSQHsZiiNCCKghwdBnPBVlWBACGRKfE4JMmH0FGOGkhYCwaTvKjYrNb0IGUo1Mom2XFFRTxAKZMgBH+bTdGE6RwQ+BuBilKxCE5bJiEAIfkECQMAHwAsAAAAABAAEAAABVzgJ45kaX5DkgyEdIqAFjja0WGnswVC/xEJHImz4ZgIncVoETCeDoTRQ6A8EQ4vkyT6cWWrItaLCRhdX5wAadHhkgCBTAmTaPE4gkDZBOkcNIAaXidbByoHg1lZIQAh+QQJAwAfACwAAAAAEAAQAAAFWeAnjmRpikQyFMspPk3lEccBnVUQCBWa3KRcxlQ4tESLQMN1IIwgAdenMEBBeqcFJCH6SR2eg2glDQxR4lMmOlocCqY1oLR1PgQC3ZleI4AzR1lUCWlShiIhACH5BAkDAB8ALAAAAAAQABAAAAVe4CeOZGmSA7Gc4pJxH3F02NlsWzMySU3esBKhsxI9NkHTgSE6WDash0yU0ChYCk2CatGdstvPgcD6bDQH0fQE2CwArQ6zxNlkSphEoaXBwU0YHWMSDXdlDwxLZYslIQAh+QQJAwAfACwAAAAAEAAQAAAFW+AnjmRpltByisszDh10QopDEvG4SJVSqbcOkDBQyEySA0E0QChWCsRA1KmtBI4O1SFYVbJMhO0kkIoIhxVNyeosSYsegCAZQdw6hQIw6xzqHxlAJwsEgyuIJSEAIfkECQMAHwAsAAAAABAAEAAABVHgJ45kaZ7oGUUltnBcGXXZuHTaFpML0kkiRkdn4mw6DFFHsTktNj6lYlfUdKQaFMeqRGwWJ+j1I9wAS8YOQXRLosVg0Yw1eugI9NEsxe/7PyEAIfkECQMAHwAsAAAAABAAEAAABU7gJ45kaZ7ntKDllBjmMk3jklCKqSDJ+hEdhcVkUXQIIgoid9pRRAkhyuJIQBWeaTWJQykoz19wWCoeRTYEeST59kauk+XFIhFo9bz+EwIAIfkECQMAHwAsAAAAABAAEAAABVjgJ45kWUqLqQIdo5ZMQpQaVy6tySkck36EgyKjUxxcnwNC8SspEAdRQtF4NTQJKdWq6YiUCtXiGf0whLbS7jD7SA679EjSbYoygBIhgXx9O3l+IwwSgoIhACH5BAkDAB8ALAAAAAAQABAAAAVi4CeOZFkuBGGaWHdgorSsQKKOjpKdXXE2CgCJcHisOIqFRHRQKFYfQeEgSjgEUI8mIeooPFCBg/tpYleKqQgTUXCOhwgJ2DAVOktSRlEfERJCJjMfCxEHHTBQIgAHBIOKiiEAIfkECQMAHwAsAAAAABAAEAAABV/gJ45kWUrRkSwmOXVHMWVA+0VJMTbbVpOLjq7E2WRIhYMNsCENCqyWcdTRaGwKzUCU0Ciwmo5IwrNlClvRw9daxEg8TnRU6MxFvKmokJi0MxkTSR1+Nh98BxEShowfIQAh+QQJAwAfACwAAAAAEAAQAAAFWuAnjmRZLsWQJJE5Rt1ReI61uAYrOoIQACVJp2AC/EiRg+tjJA2Iy6PoYFh+BBaSxbGUACaj5rKgFD2kpkNrZEGPCp2bNhAQAD+RBNj0oF3IHWsuBytPclZWIQAh+QQJAwAfACwAAAAAEAAQAAAFV+AnjmRZLsWRJEdhikZ3aLRGvFPiCoIjBIBT51YCAEmEw+uTCZAmhuUikFmaAAur9iMJZF9f0bT6OhBFzWCJ0AmLHAGeoEpITK4IRC/ZuZtQKiwFblslIQAh+QQJAwAfACwAAAAAEAAQAAAFT+AnjmRZLsWRJEchmV/UHYjjIEe3lEYSBQGPB2g5dQqf1wgQAPBgnyEU5pxar9irwJP9MKuiXeTAcwA9Zk/BV8LMEAIBjm2SpFaHyK5bCgEAIfkECQMAHwAsAAAAABAAEAAABUvgJ45kWS4UlSTpYn5TdyCOgxzdVBpsEFQVQUAl2QkqpErg8noBis2odEqtvqBTw+U3WlQApkkC4fN4fJRDWIbYbG65F0rForispRAAIfkECQMAHwAsAAAAABAAEAAABVLgJ45kWS7UkSRHtJif1B2U5yFqBF9BUFU9REJHAgQao0WjQemUAADYJyqtWq/Y7EhCkQJe2+GjAf4AqaNIgtKz9dCkyAwhCOAO0kVExaJItCYhACH5BAkDAB8ALAAAAAAQABAAAAVY4CeOZGkWR5IcxWKOBeJ5yNFhLxAEnhM4hQSOpAOQHp9CxyVa8F4L1mjhYZoymZd2yy0MXxISSusJkBadgkkXJmESBZ2jHDCaMJ0DQiBAZLULKCoHX1xaIQAh+QQJAwAfACwAAAAAEAAQAAAFXeAnjmRpfkWSHMRyikvjOMXRMScXCHz1MYlGSQcwUTquUYDzOuBEuhfqIMoAmC8ARZTISCXJz6HwWgS8IgY1FwhLOuRSJiAkNRI4AE8Q8JkYHWMOAg1FZTUqYVKLIQAh+QQJAwAfACwAAAAAEAAQAAAFXeAnjmRpilAyFMspLtllFUcHnUAQCJdYJDdSJpAxFTqtF/G0OBR8kIDrUxiIEg6B62HpiDqW4kngSIhWUyLAd3ANH6NF51kaWkqXBP0h0N1NEB1OAGFwWzR0U4ojIQAh+QQJAwAfACwAAAAAEAAQAAAFYOAnjmRpkgOxnGMGfNjRYWcWBNxIdFAJBJkVaScREQaB1+lAEA0QAhagMBB1NNGTQNOxYlnb7ueAyJ0CiOqHcGDZIL2PpNMkLTgBTQlCH0luZnsdB0ULGSwiCwRFiI0lIQAh+QQJAwAfACwAAAAAEAAQAAAFXOAnjmRplpJ0issyFl1WAh+wNWSRRKOUXDcTzPWJHDacU+NQEB0QitUGcRAlELiTQtGxYlcNRcKJ2KwUVFHhoHiYGpsD77PozEnwpUoUSTR7GzImGB00K4eIiSMhACH5BAkDAB8ALAAAAAAQABAAAAVU4CeOZGmeJ5BO5ZRYWZsY49IhSkwuSCeJhIPCYrooDgTRAXdaKBAHUcdBPHkUCVGCirIoOkrc4vSMfoJDkxcpsiEupcyzMxa56rXMgUX6of6AgSUhACH5BAkDAB8ALAAAAAAQABAAAAVX4CeOZFlKZvpdiaGSU8KYkjGNUocI5aMgnYWIcVBYTB7FgSA6IBRCk0IpSjg8qmSn6jimkonmM7WYHobFi8minH0WnYvCRboAUSKhAT9i0V9NaoCDhB8hACH5BAkDAB8ALAAAAAAQABAAAAVZ4CeOZFku1GSaU3cYK9kWpQCcHV02SiMtosJBITHxBrqBRhETKAaijsMR8yg6UYcl5hBgP0pBTKGBfgo8yMpx0IkAPVOhAyTBb6O5KiaSDDowfCILBXWCMSEAIfkECQMAHwAsAAAAABAAEAAABVrgJ45kWS7E0ZnllRyE8UkL6xJkEFhnh5MLy4Y3SrE+kI1kdEAIjgHPQZRwPFkCR4JqPWa3HwLkarIgpqPAkgUjSQKQGgnRkY8AunU4ATnGUSoXRyMXMHaDRyEAIfkECQMAHwAsAAAAABAAEAAABV/gJ45kWS7FkCSGOULdUUAet7hQUoxOEDQlSWdXagQqpMLB9YEEHqMB0RWAjBIeD1NQIS4aAmam0hllAsxParQIZJgyEsBtKnRucp9AIiokrCYLY10HHYBMAx1SeGlpIQA7"
                width="16" height="16" alt="Please wait..."/> Please wait...
    </div>
    <div id="tab-existing" class="tab-content hidden">
        <div id="job-tabs">
            <span class="active" data-action="new">New Job</span><span data-action="existing">Existing Job</span>
        </div>
        <table>
            <tr id="jobList" class="hidden">
                <th>
                    <lable for="jobSelect">Existing jobs</lable>
                </th>
                <td>
                    <select id="jobSelect"></select>
                </td>
            </tr>
            <tr>
                <th><label for="name-sm">Name</label></th>
                <td><input id="name-sm" name="jobName" type="text"/></td>
            </tr>
            <tr>
                <th><label for="description-sm">Description</label</th>
                <td><textarea id="description-sm" name="description"></textarea></td>
            </tr>
            <tr>
                <th><label for="dueDate">Due Date</label</th>
                <td><input type="text" id="dueDate" name="dueDate"/></td>
            </tr>
            <tr>
                <th><label for="cbAuthorize">Authorize Job</label</th>
                <td><input type="checkbox" class="authorize" id="cbAuthorize"
                           name="cbAuthorize" <?= $data['profile']->getAutoAuthorize() ? 'checked="checked"' : ''; ?>/>
                </td>
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
                    $contentType = $data['contentType'];

                    $locales = $profile->getTargetLocales();
                    \Smartling\Helpers\ArrayHelper::sortLocales($locales);
                    foreach ($locales as $locale) {
                        /**
                         * @var \Smartling\Settings\TargetLocale $locale
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
                                false,
                                true,
                                '',
                                [
                                    'data-smartling-locale' => $locale->getSmartlingLocale(),
                                ]
                            ); ?>
                        </p>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th class="center" colspan="2">
                    <div id="error-messages"></div>
                    <button class="button button-primary" id="createJob"
                            title="Create a new job and add content into it">Create Job
                    </button>
                    <button class="button button-primary hidden" id="addToJob"
                            title="Add content into your chosen job">Add to selected Job
                    </button>
                </th>
            </tr>
            <input type="hidden" id="timezone-sm" name="timezone-sm" value="UTC"/>
        </table>

    </div>


</div>
<?php
$id = 0;
$baseType = 'unknown';
global $post;
if ($post instanceof WP_Post) {
    $id = $post->ID;
    $baseType = 'post';
} else {
    global $tag;
    if ($tag instanceof WP_Term) {
        $id = $tag->term_id;
        $baseType = 'taxonomy';
    }
}
?>
<script>
    (function ($) {
        var currentContent = {
            'contentType': '<?= $contentType ?>',
            'id': [<?= $id ?>],
        };

        var Helper = {
            placeHolder: {
                cls: 'hidden',
                placeholderId: 'placeholder',
                contentId: 'tab-existing',
                show: function () {
                    $('#' + this.placeholderId).removeClass(this.cls);
                    $('#' + this.contentId).addClass(this.cls);
                },
                hide: function () {
                    $('#' + this.placeholderId).addClass(this.cls);
                    $('#' + this.contentId).removeClass(this.cls);
                }
            },
            queryProxy: {
                baseEndpoint: '<?= admin_url('admin-ajax.php') ?>?action=smartling_job_api_proxy',
                query: function (action, params, success) {
                    var data = {'innerAction': action, 'params': params};
                    $.post(this.baseEndpoint, data, function (response) {
                        var cb = success;
                        cb(response);
                    });
                },
                listJobs: function (cb) {
                    this.query('list-jobs', {}, function (response) {
                        response = JSON.parse(response);
                        if (200 == response.status) {
                            if (typeof cb === 'function') {
                                cb(response.data);
                            }
                        } else {
                            fail(response.message);
                        }
                    });
                },

                createJob: function (name, description, dueDate, locales, authorize, timezone, success, fail) {
                    this.query('create-job', {
                        jobName: name,
                        description: description,
                        dueDate: dueDate,
                        locales: locales,
                        authorize: authorize,
                        timezone: timezone
                    }, function (response) {
                        response = JSON.parse(response);
                        if (response.status <= 300) {
                            if (typeof success === 'function') {
                                success(response.data);
                            }
                        }
                        else if (response.status >= 400) {
                            if (typeof fail === 'function') {
                                fail(response.message);
                            }
                        }
                    });
                }
            },
            ui: {
                displayJobList: function () {
                    Helper.placeHolder.show();
                    Helper.queryProxy.listJobs(this.renderJobListInDropDown);
                },
                getSelecterTargetLocales: function () {
                    var locales = [];
                    var checkedLocales = $('.job-wizard .mcheck:checkbox:checked');
                    checkedLocales.each(
                        function (e) {
                            locales.push($(checkedLocales[e]).attr('data-blog-id'));
                        }
                    );
                    locales = locales.join(',');
                    return locales;
                },
                createJobForm: {
                    cls: 'hidden',
                    btnAdd: 'addToJob',
                    btnCreate: 'createJob',
                    show: function () {
                        $('#' + this.btnCreate).removeClass(this.cls);
                        $('#' + this.btnAdd).addClass(this.cls);
                        $('#jobList').addClass(this.cls);
                    },
                    hide: function () {
                        $('#' + this.btnCreate).addClass(this.cls);
                        $('#' + this.btnAdd).removeClass(this.cls);
                        $('#jobList').removeClass(this.cls);
                    }
                },
                renderOption: function (id, name, description, dueDate, locales) {
                    description = description == null ? '' : description;

                    // Format due date from UTC to user's local time.
                    if (dueDate == null) {
                        dueDate = '';
                    }
                    else {
                        dueDate = moment.utc(dueDate).toDate();
                        dueDate = moment(dueDate).format('YYYY-MM-DD HH:mm');
                    }

                    $option = '<option value="' + id + '" description="' + description + '" dueDate="' + dueDate + '" targetLocaleIds="' + locales + '">' + name + '</option>';
                    return $option;
                },
                renderJobListInDropDown: function (data) {
                    $('#jobSelect').html('');
                    data.forEach(function (job) {
                        $option = Helper.ui.renderOption(job.translationJobUid, job.jobName, job.description, job.dueDate, job.targetLocaleIds.join(','));
                        $('#jobSelect').append($option);
                    });
                    Helper.placeHolder.hide();
                },
                localeCheckboxes: {
                    clear: function () {
                        $('.job-wizard .mcheck:checkbox:checked').each(function (i, el) {
                            $(el).removeAttr('checked');
                        });
                    },
                    set: function (locales) {
                        this.clear();
                        var localeList = locales.split(',');

                        for (var ind in localeList) {
                            var $elements = $('.job-wizard .mcheck:checkbox[data-smartling-locale="' + localeList[ind] + '"]');

                            if (0 < $elements.length) {
                                $($elements[0]).attr('checked', 'checked');
                            }

                        }

                    }
                },
                jobForm: {
                    elements: [
                        'name-sm', 'description-sm', 'dueDate', 'authorize'
                    ],
                    clear: function () {
                        this.elements.forEach(function (el) {
                            $('#' + el).val('');
                        });
                    }
                }
            }
        };


        $(document).ready(function () {
            // emulate tabs
            $('div#job-tabs span').on('click', function () {
                var $action = $(this).attr('data-action');
                $('div#job-tabs span').removeClass('active');
                $(this).addClass('active');
                switch ($action) {
                    case 'new':
                        Helper.ui.createJobForm.show();
                        break;
                    case 'existing':
                        Helper.ui.createJobForm.hide();
                        break;
                    default:
                }
            });

            $('#timezone-sm').val(moment.tz.guess());

            var timezone = $('#timezone-sm').val();

            $('#jobSelect').select2({
                placeholder: 'Please select a job',
                allowClear: false
            });

            Helper.ui.displayJobList();

            $('#dueDate').datetimepicker({
                format: 'Y-m-d H:i',
                minDate: 0
            });

            $('#addToJob').on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();
                var jobId = $('#jobSelect').val();
                var jobName = $('input[name="jobName"]').val();
                var jobDescription = $('textarea[name="description"]').val();
                var jobDueDate = $('input[name="dueDate"]').val();

                var locales = Helper.ui.getSelecterTargetLocales();

                var createHiddenInput = function (name, value) {
                    return createInput('hidden', name, value);
                };

                var createInput = function (type, name, value) {
                    return '<input type="' + type + '" name="' + name + '" value="' + value + '" />';
                };

                var formSelector = $('#post').length ? 'post' : 'edittag';
                var isBulkSubmitPage = $('form#bulk-submit-main').length;

                // Support for bulk submit form.
                if (isBulkSubmitPage) {
                    formSelector = 'bulk-submit-main';
                    currentContent.id = $("input.bulkaction:checked").map(function () {
                        return $(this).val();
                    }).get();

                    $('#action').val('send');
                }

                // Add hidden fields only if validation is passed.
                if (currentContent.id.length) {
                    $('#' + formSelector).append(createHiddenInput('smartling[ids]', currentContent.id));
                    $('#' + formSelector).append(createHiddenInput('smartling[locales]', locales));
                    $('#' + formSelector).append(createHiddenInput('smartling[jobId]', jobId));
                    $('#' + formSelector).append(createHiddenInput('smartling[jobName]', jobName));
                    $('#' + formSelector).append(createHiddenInput('smartling[jobDescription]', jobDescription));
                    $('#' + formSelector).append(createHiddenInput('smartling[jobDueDate]', jobDueDate));
                    $('#' + formSelector).append(createHiddenInput('smartling[timezone]', timezone));
                    $('#' + formSelector).append(createHiddenInput('smartling[authorize]', $('.authorize:checked').length > 0));
                    $('#' + formSelector).append(createHiddenInput('sub', 'Upload'));
                }

                $('#' + formSelector).submit();

                // Support for bulk submit form.
                if (isBulkSubmitPage && currentContent.id.length) {
                    $('input[type="submit"]').click();
                }
            });

            $('#createJob').on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();

                var name = $('#name-sm').val();
                var description = $('#description-sm').val();
                var dueDate = $('#dueDate').val();
                var locales = [];
                var authorize = $('.authorize:checked').length > 0;

                var checkedLocales = $('.job-wizard .mcheck:checkbox:checked');

                checkedLocales.each(
                    function (e) {
                        locales.push($(checkedLocales[e]).attr('data-blog-id'));
                    }
                );


                locales = locales.join(',');
                $('#error-messages').html('');

                Helper.queryProxy.createJob(name, description, dueDate, locales, authorize, timezone, function (data) {
                    var $option = Helper.ui.renderOption(data.translationJobUid, data.jobName, data.description, data.dueDate, data.targetLocaleIds.join(','));
                    $('#jobSelect').append($option);
                    $('#jobSelect').val(data.translationJobUid);
                    $('#jobSelect').change();

                    $('#addToJob').click();

                }, function (data) {
                    var messages = [];
                    if (undefined !== data['global']) {
                        messages.push(data['global']);
                    }
                    for (var i in data) {
                        if ('global' === i) {
                            continue;
                        } else {
                            messages.push(data[i]);
                        }
                    }
                    var text = '<span>' + messages.join('</span><span>') + '</span>';
                    $('#error-messages').html(text);
                });

            });

            $('#jobSelect').on('change', function () {
                Helper.ui.localeCheckboxes.clear();
                Helper.ui.jobForm.clear();
                $('#dueDate').val($('option[value=' + $('#jobSelect').val() + ']').attr('dueDate'));
                $('#name-sm').val($('option[value=' + $('#jobSelect').val() + ']').html());
                $('#description-sm').val($('option[value=' + $('#jobSelect').val() + ']').attr('description'));
                Helper.ui.localeCheckboxes.clear();
                Helper.ui.localeCheckboxes.set($('option[value=' + $('#jobSelect').val() + ']').attr('targetlocaleids'))
            });
        })
    })(jQuery)
</script>