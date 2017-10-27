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

    #placeholder {
        width: 100%;
        font-size: larger;
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
                    <th><label for="name-sm">Name</label></th>
                    <td><input id="name-sm" type="text"/></td>
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
            </table>
        </form>

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
                        'name-sm', 'description-sm', 'dueDate', 'authorize'
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

            $('#jobSelect').select2({
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
                minDate: 0
            });

            $('#addToJob').on('click', function (e) {
                e.stopPropagation();
                e.preventDefault();
                var jobId = $('#jobSelect').val();

                var locales = Helper.ui.getSelecterTargetLocales();

                var createHiddenInput = function (name, value) {
                    return createInput('hidden', name, value);
                };

                var createInput = function (type, name, value) {
                    return '<input type="' + type + '" name="' + name + '" value="' + value + '" />';
                };

                var formSelector = 0 > $('#post').length ? 'post' : 'edittag';

                $('#' + formSelector).append(createHiddenInput('smartling[ids]', currentContent.id));
                $('#' + formSelector).append(createHiddenInput('smartling[locales]', locales));
                $('#' + formSelector).append(createHiddenInput('smartling[jobId]', jobId));
                $('#' + formSelector).append(createHiddenInput('smartling[authorize]', $('.authorize:checked').length > 0));
                $('#' + formSelector).append(createHiddenInput('sub', 'Upload'));
                $('#' + formSelector).submit();
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
                    $('#name-sm').val($('option[value=' + $('#jobSelect').val() + ']').html());
                    $('#description-sm').val($('option[value=' + $('#jobSelect').val() + ']').attr('description'));
                    Helper.ui.localeCheckboxes.clear();
                    Helper.ui.localeCheckboxes.set($('option[value=' + $('#jobSelect').val() + ']').attr('targetlocaleids'))
                }
            });
        })
    })(jQuery)
</script>