<?php
/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>
<?php
global $tag;
$needWrapper = ($tag instanceof WP_Term);
?>


<?php if($needWrapper) : ?>
<div class="postbox-container">
    <div id="panel-box" class="postbox hndle"><h2><span>Translate content</span></h2>
        <div class="inside">
<?php endif; ?>

<div class="job-wizard">
    <div id="placeholder"><span class="loader"></span> Please wait...</div>
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
                <td><textarea id="description-sm" name="description-sm"></textarea></td>
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
                    <div class="locale-list">
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
                        <p class="locale-list">
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
                    </div>
                </td>
            </tr>
            <tr>
                <th class="center" colspan="2">

                    <div id="error-messages"></div>
                    <div id="loader-image" class="hidden"><span class="loader"></span></div>
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

<?php if($needWrapper) : ?>
        </div>
    </div>
</div>
<?php endif; ?>


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
                    $('#loader-image').removeClass('hidden');
                    this.query('create-job', {
                        jobName: name,
                        description: description,
                        dueDate: dueDate,
                        locales: locales,
                        authorize: authorize,
                        timezone: timezone
                    }, function (response) {
                        $('#loader-image').addClass('hidden');
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
                var jobDescription = $('textarea[name="description-sm"]').val();
                var jobDueDate = $('input[name="dueDate"]').val();

                if ('' !== jobDueDate) {
                    var nowTS = Math.floor((new Date()).getTime() / 1000);
                    var formTS = Math.floor(moment(jobDueDate, 'YYYY-MM-DD HH:mm').toDate().getTime() / 1000);
                    if (nowTS >= formTS) {
                        alert("Invalid Due Date value. It cannot be in the past!.");
                        return false;
                    }
                }

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