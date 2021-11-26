<?php

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Services\ContentRelationsHandler;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\WP\Table\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;

/**
 * @var WPAbstract $this
 * @var BulkSubmitTableWidget $data
 * @var ConfigurationProfileEntity $profile
 */
$data = $this->getViewData();
$profile = $data['profile'];

?>
<?php
$screen = get_current_screen();
$isBulkSubmitPage = false;
if ($screen !== null) {
    $isBulkSubmitPage = $screen->id === 'smartling_page_smartling-bulk-submit';
}
global $tag;
$needWrapper = ($tag instanceof WP_Term);
?>

<script>
    const handleRelationsManually = <?= GlobalSettingsManager::isHandleRelationsManually() ? 'true' : 'false' ?>;
    const isBulkSubmitPage = <?= $isBulkSubmitPage ? 'true' : 'false'?>;
    let l1Relations = {missingTranslatedReferences: {}, originalReferences: {}};
    let l2Relations = {missingTranslatedReferences: {}, originalReferences: {}};
</script>

<?php if ($needWrapper) : ?>
<div class="postbox-container">
    <div id="panel-box" class="postbox hndle"><h2><span>Translate content</span></h2>
        <div class="inside">
            <?php endif; ?>

            <div class="job-wizard">
                <div id="placeholder"><span class="loader"></span> Please wait...</div>
                <div id="tab-existing" class="tab-content hidden">
                    <div id="job-tabs">
                        <span class="active" data-action="new">New Job</span>
                        <span data-action="existing">Existing Job</span>
                        <?= $isBulkSubmitPage ? '' : '<span data-action="clone">Clone</span>'?>
                    </div>
                    <table>
                        <tr id="jobList" class="hidden hideWhenCloning">
                            <th>
                                <label for="jobSelect">Existing jobs</label>
                            </th>
                            <td>
                                <select id="jobSelect"></select>
                            </td>
                        </tr>
                        <tr class="hideWhenCloning">
                            <th><label for="name-sm">Name</label></th>
                            <td><input id="name-sm" name="jobName" type="text"/></td>
                        </tr>
                        <tr class="hideWhenCloning">
                            <th><label for="description-sm">Description</label</th>
                            <td><textarea id="description-sm" name="description-sm"></textarea></td>
                        </tr>
                        <tr class="hideWhenCloning">
                            <th><label for="dueDate">Due Date</label</th>
                            <td><input type="text" id="dueDate" name="dueDate"/></td>
                        </tr>
                        <tr class="hideWhenCloning">
                            <th><label for="cbAuthorize">Authorize Job</label</th>
                            <td><input type="checkbox" class="authorize" id="cbAuthorize"
                                       name="cbAuthorize" <?= $profile->getAutoAuthorize() ? 'checked="checked"' : '' ?>/>
                            </td>
                        </tr>

                        <?php
                        $contentType = $data['contentType'];

                        $locales = $profile->getTargetLocales();
                        ArrayHelper::sortLocales($locales);
                        ?>
                        <tr>
                            <th>Target Locales</th>
                            <td>
                                <div>
                                    <?= WPAbstract::checkUncheckBlock() ?>
                                </div>
                                <div class="locale-list">
                                    <?php

                                    $localeList = [];

                                    foreach ($locales as $locale) {
                                        if (!$locale->isEnabled()) {
                                            continue;
                                        }

                                        $localeList[] = $locale->getBlogId();
                                        ?>
                                        <p class="locale-list">
                                            <?= WPAbstract::localeSelectionCheckboxBlock(
                                                'bulk-submit-locales',
                                                $locale->getBlogId(),
                                                $locale->getLabel(),
                                                false,
                                                true,
                                                '',
                                                [
                                                    'data-smartling-locale' => $locale->getSmartlingLocale(),
                                                ]
                                            ) ?>
                                        </p>
                                    <?php } ?>
                                    <script>
                                        var localeList = "<?= implode(',', $localeList)?>";
                                    </script>
                                </div>
                            </td>
                        </tr>
                        <?php if (!$isBulkSubmitPage && GlobalSettingsManager::isHandleRelationsManually()) { ?>
                            <tr>
                                <th>Related content</th>
                                <td>
                                    <?= HtmlTagGeneratorHelper::tag(
                                        'select',
                                        HtmlTagGeneratorHelper::renderSelectOptions(
                                            0,
                                            [
                                                0 => 'Don\'t send  related content',
                                                1 => 'Send related content one level deep',
                                                2 => 'Send related content two levels deep',
                                            ]
                                        ),
                                        [
                                            'id' => 'cloneDepth',
                                            'name' => 'cloneDepth',
                                        ],
                                    )?>
                                </td>
                            </tr>
                            <tr id="relationsInfo">
                                <th>New content to be uploaded:</th>
                                <td id="relatedContent">
                                </td>
                            </tr>

                        <?php } ?>
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
                                <button class="button button-primary hidden" id="cloneButton">Clone</button>
                            </th>
                        </tr>
                        <input type="hidden" id="timezone-sm" name="timezone-sm" value="UTC"/>
                    </table>

                </div>


            </div>

            <?php if ($needWrapper) : ?>
        </div>
    </div>
</div>
<?php endif; ?>


<?php
$id       = 0;
$baseType = 'unknown';
global $post;
if ($post instanceof WP_Post) {
    $id       = $post->ID;
    $baseType = 'post';
} else {
    global $tag;
    if ($tag instanceof WP_Term) {
        $id       = $tag->term_id;
        $baseType = 'taxonomy';
    }
}
?>
<script>
    (function ($) {
        var currentContent = {
            "contentType": '<?= $contentType ?>',
            "id": [<?= $id ?>]
        };

        var Helper = {
            placeHolder: {
                cls: "hidden",
                placeholderId: "placeholder",
                contentId: "tab-existing",
                show: function () {
                    $("#" + this.placeholderId).removeClass(this.cls);
                    $("#" + this.contentId).addClass(this.cls);
                },
                hide: function () {
                    $("#" + this.placeholderId).addClass(this.cls);
                    $("#" + this.contentId).removeClass(this.cls);
                }
            },
            queryProxy: {
                baseEndpoint: '<?= admin_url('admin-ajax.php') ?>?action=smartling_job_api_proxy',
                query: function (action, params, success) {
                    var data = { "innerAction": action, "params": params };
                    $.post(this.baseEndpoint, data, function (response) {
                        success(response);
                    });
                },
                listJobs: function (cb) {
                    this.query("list-jobs", {}, function (response) {
                        if (200 == response.status) {
                            if (typeof cb === "function") {
                                cb(response.data);
                            }
                        } else {
                            fail(response.message);
                        }
                    });
                },

                createJob: function (name, description, dueDate, locales, authorize, timezone, success, fail) {
                    $("#loader-image").removeClass("hidden");
                    this.query("create-job", {
                        jobName: name,
                        description: description,
                        dueDate: dueDate,
                        locales: locales,
                        authorize: authorize,
                        timezone: timezone
                    }, function (response) {
                        $("#loader-image").addClass("hidden");
                        if (response.status <= 300) {
                            if (typeof success === "function") {
                                success(response.data);
                            }
                        } else if (response.status >= 400) {
                            if (typeof fail === "function") {
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
                getSelectedTargetLocales: function () {
                    var locales = [];
                    $(".job-wizard .mcheck:checkbox:checked").each(
                        function (i, e) {
                            locales.push($(e).attr("data-blog-id"));
                        }
                    );
                    locales = locales.join(",");
                    return locales;
                },
                createJobForm: {
                    cls: "hidden",
                    btnAdd: "addToJob",
                    btnCreate: "createJob",
                    show: function () {
                        $("#" + this.btnCreate).removeClass(this.cls);
                        $("#" + this.btnAdd).addClass(this.cls);
                        $("#jobList").addClass(this.cls);
                    },
                    hide: function () {
                        $("#" + this.btnCreate).addClass(this.cls);
                        $("#" + this.btnAdd).removeClass(this.cls);
                        $("#jobList").removeClass(this.cls);
                    }
                },
                renderOption: function (id, name, description, dueDate, locales) {
                    description = description == null ? "" : description;

                    // Format due date from UTC to user's local time.
                    if (dueDate == null) {
                        dueDate = "";
                    } else {
                        dueDate = moment.utc(dueDate).toDate();
                        dueDate = moment(dueDate).format("YYYY-MM-DD HH:mm");
                    }

                    return "<option value=\"" + id + "\" description=\"" + description + "\" dueDate=\"" + dueDate + "\" targetLocaleIds=\"" + locales + "\">" + name + "</option>";
                },
                renderJobListInDropDown: function (data) {
                    $("#jobSelect").html("");
                    data.forEach(function (job) {
                        $("#jobSelect").append(Helper.ui.renderOption(job.translationJobUid, job.jobName, job.description, job.dueDate, job.targetLocaleIds.join(",")));
                    });
                    Helper.placeHolder.hide();
                },
                localeCheckboxes: {
                    clear: function () {
                        $(".job-wizard .mcheck:checkbox:checked").each(function (i, el) {
                            $(el).removeAttr("checked");
                        });
                    },
                    set: function (locales) {
                        this.clear();
                        var localeList = locales.split(",");

                        for (var ind in localeList) {
                            var $elements = $(".job-wizard .mcheck:checkbox[data-smartling-locale=\"" + localeList[ind] + "\"]");

                            if (0 < $elements.length) {
                                $($elements[0]).prop("checked", true);
                            }

                        }

                    }
                },
                jobForm: {
                    elements: [
                        "name-sm", "description-sm", "dueDate", "authorize"
                    ],
                    clear: function () {
                        this.elements.forEach(function (el) {
                            $("#" + el).val("");
                        });
                    }
                }
            }
        };

        $(document).ready(function () {
            // emulate tabs
            $("div#job-tabs span").on("click", function () {
                $("div#job-tabs span").removeClass("active");
                $(this).addClass("active");
                const hideWhenCloning = $('.hideWhenCloning');
                const cloneButton = $('#cloneButton');
                switch ($(this).attr("data-action")) {
                    case "new":
                        Helper.ui.createJobForm.show();
                        hideWhenCloning.show();
                        cloneButton.addClass('hidden');
                        break;
                    case "clone":
                        Helper.ui.createJobForm.hide();
                        hideWhenCloning.hide();
                        $('#addToJob').addClass('hidden');
                        cloneButton.removeClass('hidden');
                        break;
                    case "existing":
                        Helper.ui.createJobForm.hide();
                        hideWhenCloning.show();
                        cloneButton.addClass('hidden');
                        break;
                    default:
                }
            });

            var timezoneEl = $("#timezone-sm");
            timezoneEl.val(moment.tz.guess());

            var timezone = timezoneEl.val();

            var jobSelectEl = $("#jobSelect");
            jobSelectEl.select2({
                placeholder: "Please select a job",
                allowClear: false
            });

            Helper.ui.displayJobList();

            $("#dueDate").datetimepicker2({
                format: "Y-m-d H:i",
                minDate: 0
            });

            const mergeRelations = function mergeRelations(a, b) {
                a = a || {};
                b = b || {};
                const result = JSON.parse(JSON.stringify(a));
                for (const blogId in b) {
                    if (!result.hasOwnProperty(blogId)) {
                        result[blogId] = {};
                    }
                    for (const type in b[blogId]) {
                        if (!result[blogId].hasOwnProperty(type)) {
                            result[blogId][type] = [];
                        }
                        result[blogId][type] = result[blogId][type].concat(b[blogId][type]).filter((value, index, self) => self.indexOf(value) === index);
                    }
                }

                return result;
            }

            const recalculateRelations = function recalculateRelations() {
                $("#relatedContent").html("");
                const relations = {};
                let missingRelations = {};
                const cloneDepth = $('#cloneDepth').val();
                switch (cloneDepth) {
                    case "0":
                        return;
                    case "1":
                        missingRelations = l1Relations.missingTranslatedReferences;
                        break;
                    case "2":
                        missingRelations = mergeRelations(l1Relations.missingTranslatedReferences, l2Relations.missingTranslatedReferences);
                        break;
                    default:
                        console.error(`Unsupported clone depth value: ${cloneDepth}`);
                }
                const buildRelationsHint = function (relations) {
                    let html = "";
                    for (const type in relations) {
                        html += `${type} (${relations[type]}) </br>`;
                    }
                    return html;
                };
                $(".job-wizard input.mcheck[type=checkbox]:checked").each(function () {
                    const blogId = this.dataset.blogId;
                    if (missingRelations && missingRelations.hasOwnProperty(blogId)) {
                        for (const sysType in missingRelations[blogId]) {
                            let sysCount = missingRelations[blogId][sysType].length;
                            if (relations.hasOwnProperty(sysType)) {
                                relations[sysType] += sysCount;
                            } else {
                                relations[sysType] = sysCount;
                            }
                            $("#relatedContent").html(buildRelationsHint(relations));
                        }
                    }
                });
            };

            const loadRelations = function loadRelations(contentType, contentId, level = 1) {
                const url = `${ajaxurl}?action=<?= ContentRelationsHandler::ACTION_NAME?>&id=${contentId}&content-type=${contentType}&targetBlogIds=${localeList}`;

                $.get(url, function loadData(data) {
                    if (data.response.data) {
                        switch (level) {
                            case 1:
                                l1Relations = data.response.data;
                                for (const relatedType in l1Relations.originalReferences) {
                                    for (const relatedId of l1Relations.originalReferences[relatedType]) {
                                        loadRelations(relatedType, relatedId, level + 1);
                                    }
                                }
                                window.relationsInfo = data.response.data;
                                break;
                            case 2:
                                const originalReferences = data.response.data.originalReferences;
                                for (const relatedType in originalReferences) {
                                    for (const relatedId of originalReferences[relatedType]) {
                                        if (!l2Relations.originalReferences.hasOwnProperty(relatedType)) {
                                            l2Relations.originalReferences[relatedType] = [];
                                        }
                                        l2Relations.originalReferences[relatedType] = l2Relations.originalReferences[relatedType].concat(originalReferences[relatedType]);
                                    }
                                    l2Relations.missingTranslatedReferences = mergeRelations(
                                        l2Relations.missingTranslatedReferences,
                                        data.response.data.missingTranslatedReferences,
                                    );
                                }
                                break;
                        }

                        recalculateRelations();
                    }
                });
            };

            if (handleRelationsManually && !isBulkSubmitPage) {
                loadRelations(currentContent.contentType, currentContent.id);
                $('.job-wizard input.mcheck, .job-wizard a').on('click', recalculateRelations);
                $('#cloneDepth').on('change', recalculateRelations);
            }
            var hasProp = function (obj, prop) {
                return Object.prototype.hasOwnProperty.call(obj, prop);
            };

            var canDispatch = hasProp(window, "wp")
                && hasProp(window.wp, "data")
                && hasProp(window.wp.data, "dispatch")
            ;

            if (!handleRelationsManually) {
                /*
                * Use class checking method for detecting Gutenberg as defined here https://github.com/WordPress/gutenberg/issues/12200
                * This prevents conflicts with plugins that enqueue the React library when the Classic Editor is enabled.
                */
                if (document.body.classList.contains("block-editor-page")) {
                    $("#addToJob").on("click", function (e) {
                        e.stopPropagation();
                        e.preventDefault();

                        if (isBulkSubmitPage) {
                            // we're on bulk submit page and need to rebuild currentContent structure for WP 5.2+
                            currentContent.contentType = $("#smartling-bulk-submit-page-content-type").val();
                            currentContent.id = [];

                            $("input.bulkaction[type=checkbox]:checked").each(
                                function () {
                                    var id = parseInt($(this).attr("id").split("-")[0]);
                                    currentContent.id = currentContent.id.concat([id]);
                                });
                        }

                        var btnSelector = "#addToJob";
                        var wp5an = "components-button is-primary is-busy is-large";
                        var btn = $(btnSelector);

                        var defaultText = btn.val();

                        var btnLockWait = function () {
                            $(btn).addClass(wp5an);
                            $(btn).val("Please wait...");
                            $(btn).attr("disabled", "disabled");
                        };
                        var btnUnlockWait = function () {
                            $(btn).removeClass(wp5an);
                            $(btn).val(defaultText);
                            $(btn).removeAttr("disabled");
                        };

                        var checkedLocalesCbs = $("div.job-wizard input[type=checkbox].mcheck:checked");
                        var blogs = [];

                        for (var i = 0; i < checkedLocalesCbs.length; i++) {
                            var blogId = $(checkedLocalesCbs[i]).attr("data-blog-id");
                            blogs = blogs.concat([blogId]);
                        }

                        var jobSelectEl = $("#jobSelect");
                        jobSelectEl.select();

                        var obj = {
                            content: {
                                type: currentContent.contentType,
                                id: currentContent.id.join(",")
                            },
                            job: {
                                id: jobSelectEl.val(),
                                name: $("input[name=\"jobName\"]").val(),
                                description: $("textarea[name=\"description-sm\"]").val(),
                                dueDate: $("input[name=\"dueDate\"]").val(),
                                timeZone: timezone,
                                authorize: ($("div.job-wizard input[type=checkbox].authorize:checked").length > 0)
                            },
                            blogs: blogs.join(",")
                        };
                        btnLockWait();
                        $('#error-messages').hide();
                        $.post(
                            ajaxurl + "?action=" + "smartling_upload_handler",
                            obj,
                            function (data) {
                                function reportDispatchError(e) {
                                    console.log("An exception prevented proper notification for this event");
                                    console.log(JSON.stringify(wp.data.dispatch('core/notices')));
                                    console.log(e);
                                }
                                if (canDispatch) {
                                    switch (data.status) {
                                        case "SUCCESS":
                                            try {
                                                wp.data.dispatch("core/notices").createSuccessNotice("Content added to Upload queue.");
                                            } catch (e) {
                                                console.log("Content added to Upload queue.");
                                                reportDispatchError(e);
                                            }
                                            break;
                                        case "FAIL":
                                            try {
                                                wp.data.dispatch("core/notices").createErrorNotice("Failed adding content to download queue: " + data.message);
                                            } catch (e) {
                                                console.error("Failed adding content to download queue: " + data.message);
                                                reportDispatchError(e);
                                            }
                                            break;
                                        default:
                                            console.log(data);
                                    }
                                }
                                btnUnlockWait();
                            }
                        );
                    });
                } else { // document.body doesn't contain block-editor-page (non-gutenberg editor or admin page)
                    $("#addToJob").on("click", function (e) {
                        e.stopPropagation();
                        e.preventDefault();
                        var jobId = $("#jobSelect").val();
                        var jobName = $("#name-sm").val();
                        var jobDescription = $("textarea[name=\"description-sm\"]").val();
                        var jobDueDate = $("input[name=\"dueDate\"]").val();

                        if ("" !== jobDueDate) {
                            var nowTS = Math.floor((new Date()).getTime() / 1000);
                            var formTS = Math.floor(moment(jobDueDate, "YYYY-MM-DD HH:mm").toDate().getTime() / 1000);
                            if (nowTS >= formTS) {
                                alert("Invalid Due Date value. It cannot be in the past!.");
                                return false;
                            }
                        }

                        var locales = Helper.ui.getSelectedTargetLocales();

                        var createHiddenInput = function (name, value) {
                            return createInput("hidden", name, value);
                        };

                        var createInput = function (type, name, value) {
                            return "<input type=\"" + type + "\" name=\"" + name + "\" value=\"" + value + "\" />";
                        };

                        var formSelector = $("#post").length ? "post" : "edittag";
                        var isBulkSubmitPage = $("form#bulk-submit-main").length;

                        // Support for bulk submit form.
                        if (isBulkSubmitPage) {
                            formSelector = "bulk-submit-main";
                            currentContent.id = $("input.bulkaction:checked").map(function () {
                                return $(this).val();
                            }).get();

                            $("#action").val("send");
                        }

                        // Add hidden fields only if validation is passed.
                        var formSelectorEl = $("#" + formSelector);
                        if (currentContent.id.length) {
                            formSelectorEl.append(createHiddenInput("smartling[ids]", currentContent.id));
                            formSelectorEl.append(createHiddenInput("smartling[locales]", locales));
                            formSelectorEl.append(createHiddenInput("smartling[jobId]", jobId));
                            formSelectorEl.append(createHiddenInput("smartling[jobName]", jobName));
                            formSelectorEl.append(createHiddenInput("smartling[jobDescription]", jobDescription));
                            formSelectorEl.append(createHiddenInput("smartling[jobDueDate]", jobDueDate));
                            formSelectorEl.append(createHiddenInput("smartling[timezone]", timezone));
                            formSelectorEl.append(createHiddenInput("smartling[authorize]", $(".authorize:checked").length > 0));
                            formSelectorEl.append(createHiddenInput("sub", "Upload"));
                        }

                        formSelectorEl.submit();

                        // Support for bulk submit form.
                        if (isBulkSubmitPage && currentContent.id.length) {
                            $("input[type=\"submit\"]").click();
                        }
                    });
                }
            } else { // handle relations manually
                $("#addToJob, #cloneButton").on("click", function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    $('#error-messages').hide();

                    var url = `${ajaxurl}?action=smartling-create-submissions`;

                    var blogIds = [];

                    $(".job-wizard input.mcheck[type=checkbox]:checked").each(function () {
                        blogIds.push(this.dataset.blogId);
                    });

                    var data = {
                        formAction: e.target.id === 'cloneButton' ? 'clone' : 'upload',
                        source: currentContent,
                        job: {
                            id: $("#jobSelect").val(),
                            name: $("option[value=" + jobSelectEl.val() + "]").html(),
                            relations: [],
                            description: $("textarea[name=\"description-sm\"]").val(),
                            dueDate: $("input[name=\"dueDate\"]").val(),
                            timeZone: timezone,
                            authorize: ($("div.job-wizard input[type=checkbox].authorize:checked").length > 0)
                        },
                        targetBlogIds: blogIds.join(","),
                    };

                    if (!isBulkSubmitPage) {
                        switch ($('#cloneDepth').val()) {
                            case "1":
                                data.relations = l1Relations.missingTranslatedReferences;
                                break;
                            case "2":
                                data.relations = mergeRelations(l1Relations.missingTranslatedReferences, l2Relations.missingTranslatedReferences);
                                break;
                        }
                    }

                    if (isBulkSubmitPage) {
                        data.ids = [];
                        $("input.bulkaction[type=checkbox]:checked").each(function () {
                            var parts = $(this).attr("id").split("-");
                            data.ids.push(parseInt(parts.shift()));
                            data.source.contentType = parts.join("-");
                        });
                    }

                    var uiShowMessage = function (style, message) {
                        var cssStyle;
                        switch (style) {
                            case "SUCCESS":
                                cssStyle='success';
                                break;
                            case "FAILED":
                                cssStyle='error';
                                break;
                            default:
                                cssStyle='info';
                                break;
                        }
                        var msg = `<div id="smartling_upload_msg" class="notice-${cssStyle} notice is-dismissible"><p>${message}</p><button type="button" class="notice-dismiss" onclick="this.parentNode.remove()"></button></div>`;
                        $(msg).insertAfter(isBulkSubmitPage ? '#loader-image' : 'hr.wp-header-end');
                    };

                    if (document.body.classList.contains("block-editor-page")) {
                        uiShowMessage = function (style, message) {
                            if (canDispatch) {
                                switch (style) {
                                    case "SUCCESS":
                                        wp.data.dispatch("core/notices").createSuccessNotice(message);
                                        break;
                                    case "FAIL":
                                        wp.data.dispatch("core/notices").createErrorNotice(message);
                                        break;
                                    default:
                                        console.log(data);
                                }
                            }
                        };
                    }

                    var message = "Failed adding content to upload queue.";
                    $.post(url, data, function (d) {
                        if (!isBulkSubmitPage) {
                            loadRelations(currentContent.contentType, currentContent.id, localeList);
                        }
                        switch (d.status) {
                            case "SUCCESS":
                                uiShowMessage(d.status, "Content successfully added to upload queue.");
                                break;
                            case "FAILED":
                            default:
                                uiShowMessage("FAILED", message);
                                break;
                        }
                    }).fail(function (e) {
                        if (e.responseJSON && e.responseJSON.response && e.responseJSON.response.message) {
                            message = e.responseJSON.response.message;
                        }
                        $('#error-messages').html(message);
                    });
                });
            }

            $("#createJob").on("click", function (e) {
                e.stopPropagation();
                e.preventDefault();

                var name = $("#name-sm").val();
                var description = $("#description-sm").val();
                var dueDate = $("#dueDate").val();
                var locales = Helper.ui.getSelectedTargetLocales();
                var authorize = $(".authorize:checked").length > 0;

                $("#error-messages").html("");

                Helper.queryProxy.createJob(name, description, dueDate, locales, authorize, timezone, function (data) {
                    var $option = Helper.ui.renderOption(data.translationJobUid, data.jobName, data.description, data.dueDate, data.targetLocaleIds.join(","));
                    jobSelectEl.append($option);
                    jobSelectEl.val(data.translationJobUid);
                    jobSelectEl.change();

                    $("#addToJob").click();

                }, function (data) {
                    var messages = [];
                    if (undefined !== data["global"]) {
                        messages.push(data["global"]);
                    }
                    for (var i in data) {
                        if ("global" !== i) {
                            messages.push(data[i]);
                        }
                    }
                    var text = "<span>" + messages.join("</span><span>") + "</span>";
                    $("#error-messages").html(text);
                });

            });

            jobSelectEl.on("change", function () {
                Helper.ui.localeCheckboxes.clear();
                Helper.ui.jobForm.clear();
                var optionEl = $("option[value=" + jobSelectEl.val() + "]");
                $("#dueDate").val(optionEl.attr("dueDate"));
                $("#name-sm").val(optionEl.html());
                $("#description-sm").val(optionEl.attr("description"));
                Helper.ui.localeCheckboxes.clear();
                Helper.ui.localeCheckboxes.set(optionEl.attr("targetlocaleids"));
            });
        });
    })(jQuery);
</script>
