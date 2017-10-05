<?php
/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();
?>

<style>
    span.createnew {
        width: 100%;
        font-weight: bold;
        font-style: italic;
        display: inline-block;
        padding-left: 10pt;
    }

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


</style>
<div class="job-wizard">
    <div id="placeholder">Loading from Smartling. Please wait...</div>
    <div id="tab-existing" class="tab-content hidden">
        <form action="javascript:void(0);" id="frmJob" class="jobs-form">
            <table>
                <tr>
                    <th>
                        <lable for="jobSelect">Existing jobs</lable>
                    </th>
                    <td>
                        <select id="jobSelect"></select>
                    </td>
                </tr>
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
                    <td><input type="checkbox" class="authorize" id="cbAuthorize" name="cbAuthorize"/></td>
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
            </table>
        </form>

    </div>


</div>
<?php global $post; ?>
<script>
    (function ($) {
        var currentContent = {
            'contentType': '<?= $post->post_type ?>',
            'id': [<?= $post->ID ?>],
        };

        var JobWorker = function ($submissionId) {
            console.log('Sending file to smartling. Submission id=' + $submissionId);
            /**
             * Stage 1: File processing:
             */
            console.log($submissionId);
            console.log('-----------');

            Helper.queryProxy.uploadSubmission($submissionId, function (response) {
                response = JSON.parse(response);
                if (400 === response.status) {
                    console.log('Failed uploading file.');
                    return;
                }
                console.log('Uploaded submission id=' + $submissionId);
                var ownSubmission = $submissionId;

                console.log(response);
                if (response.data.submissions) {
                    for (var fileUri in response.data.submissions) {
                        console.log('Processing fileUri:' + fileUri);
                        Helper.queryProxy.buildQueue(response.data.submissions[fileUri]);
                    }
                }

                /**
                 * Stage 2: CheckStatus:
                 */

                Helper.queryProxy.checkStatus(ownSubmission, function (response) {
                    response = JSON.parse(response);
                    var ownSubmission = response.data.submission.id;
                    console.log(ownSubmission);
                    console.log('Checked uploaded file for submission id=' + $submissionId);
                    if (200 === response.status && response.data.submission) {
                        var submission = response.data.submission;
                        Helper.queryProxy.addFileToJob(ownSubmission, function (response) {
                            var submissionId = ownSubmission;
                            response = JSON.parse(response);
                            if (200 === response.status) {
                                Helper.queryProxy.unlinkSubmissions(submissionId, function (response) {
                                    /**
                                     * Here we try to authorize job if total left submissions for job = 0
                                     */
                                    response = JSON.parse(response);
                                    if (0 === response.data.left && (authorize = $('.authorize:checked').length > 0)) {
                                        Helper.queryProxy.authorizeJob(response.data.jobId, function (response) {
                                            console.log(response);
                                        });
                                    }
                                    console.log(response);
                                });
                            } else {
                                console.log(response);
                            }

                        });
                    }
                });
            });
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
                    console.log('ProxyQuery:');
                    console.log('--------------------------------------------------');
                    var data = {'innerAction': action, 'params': params};
                    console.log(data);
                    console.log('--------------------------------------------------');
                    $.post(this.baseEndpoint, data, function (response) {
                        var cb = success;
                        console.log('ProxyResponse:');
                        console.log('--------------------------------------------------');
                        console.log(response);
                        console.log('--------------------------------------------------');
                        cb(response);
                    });
                },
                buildQueue: function (submissions) {
                    console.log('Got submissions:');
                    console.log(submissions);
                    console.log('=============');

                    var submissionId = 0;
                    for (var i in submissions) {
                        var submission = submissions[i];
                        if ('New' === submission.status) {
                            console.log('Working on submission #' + submission.id);
                            submissionId = submission.id;
                            break;
                        }
                    }
                    if (submissionId > 0) {
                        JobWorker(submissionId);
                    }
                },

                processSteps: function (jobId, success, fail) {
                    var locales = Helper.ui.getSelecterTargetLocales();

                    for (var i in currentContent.id) {
                        console.log('Precessing content #' + currentContent.id[i]);
                        this.createSubmissions(currentContent.contentType, locales, currentContent.id[i], jobId, function (response) {
                            response = JSON.parse(response);
                            if (response.data.submissions) {
                                for (var fileUri in response.data.submissions) {
                                    console.log('Processing fileUri:' + fileUri);
                                    Helper.queryProxy.buildQueue(response.data.submissions[fileUri]);
                                }
                            }
                        });
                    }
                },

                createSubmissions: function (contentType, locale, contentId, jobId, cb) {
                    this.query('create-submissions', {
                        contentType: contentType,
                        targetBlogId: locale,
                        sourceId: contentId,
                        jobId: jobId
                    }, cb);
                },

                uploadSubmission: function (submissionId, cb) {
                    this.query('upload-submission', {
                        id: submissionId
                    }, cb);
                },

                checkStatus: function (submissionId, cb) {
                    this.query('check-status-submission', {
                        id: submissionId
                    }, cb);
                },

                addFileToJob: function (submissionId, cb) {
                    this.query('add-file-to-job', {
                        id: submissionId
                    }, cb);
                },

                unlinkSubmissions: function (submissionId, cb) {
                    this.query('unlink-submissions', {
                        id: submissionId
                    }, cb);
                },

                authorizeJob: function (jobId, cb) {
                    this.query('authorize-job', {id: jobId}, cb);
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
                createJob: function (name, description, dueDate, locales, authorize, success, fail) {
                    this.query('create-job', {
                        name: name,
                        description: description,
                        dueDate: dueDate,
                        locales: locales,
                        authorize: authorize
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
                    },
                    hide: function () {
                        $('#' + this.btnCreate).addClass(this.cls);
                        $('#' + this.btnAdd).removeClass(this.cls);
                    }
                },
                renderOption: function (id, name, description, dueDate, locales) {
                    $option = '<option value="' + id + '" description="' + description + '" dueDate="' + dueDate + '" targetLocaleIds="' + locales + '">' + name + '</option>';
                    return $option;
                },
                renderJobListInDropDown: function (data) {
                    console.log(data);
                    $('#jobSelect').html('');
                    var createnew = '<option class="new" value="createnew"><span class="new">Create new Job</span></option><option disabled="disabled">&nbsp;</option>';
                    $('#jobSelect').append(createnew);
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
                        'name', 'description', 'dueDate', 'authorize'
                    ],
                    clear: function () {
                        this.elements.forEach(function (el) {
                            $('#' + el).val('');
                        });
                    },
                    lock: function () {
                        this.elements.forEach(function (el) {
                            $('#' + el).attr('disabled', 'disabled');
                        });
                    },
                    unlock: function () {
                        this.elements.forEach(function (el) {
                            $('#' + el).removeAttr('disabled');
                        });
                    },
                }
            }
        };


        $(document).ready(function () {

            $('select').select2({
                placeholder: 'Please select a job',
                allowClear: false,
                templateResult: function (optionElement) {
                    if ('createnew' !== optionElement.id) {
                        return optionElement.text;
                    }
                    var $state = $('<span class="createnew">' + optionElement.text + '</span>');
                    return $state;
                }
            });

            Helper.ui.displayJobList();

            $('#dueDate').datetimepicker({
                format: 'Y-m-d H:i:s',
                //inline: true,
                minDate: 0
            });


            $('#addToJob').on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();

                var jobId = $('#jobSelect').val();

                Helper.queryProxy.processSteps(jobId,
                    function (success) {

                    },
                    function (fail) {
                    });
            });

            $('#createJob').on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();

                var name = $('#name').val();
                var description = $('#description').val();
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

                Helper.queryProxy.createJob(name, description, dueDate, locales, authorize, function (data) {
                    var $option = Helper.ui.renderOption(data.translationJobUid, data.jobName, data.description, data.dueDate, data.targetLocaleIds.join(','));
                    $('#jobSelect').append($option);
                    $('#jobSelect').val(data.translationJobUid);
                    $('#jobSelect').change();
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
                if ('createnew' === $('#jobSelect').val()) {
                    Helper.ui.jobForm.unlock();
                    Helper.ui.createJobForm.show();
                } else {
                    Helper.ui.createJobForm.hide();
                    Helper.ui.jobForm.lock();
                    $('#dueDate').val($('option[value=' + $('#jobSelect').val() + ']').attr('dueDate'));
                    $('#name').val($('option[value=' + $('#jobSelect').val() + ']').html());
                    $('#description').val($('option[value=' + $('#jobSelect').val() + ']').attr('description'));
                    Helper.ui.localeCheckboxes.clear();
                    Helper.ui.localeCheckboxes.set($('option[value=' + $('#jobSelect').val() + ']').attr('targetlocaleids'))
                }
            });
        })
    })(jQuery)
</script>
