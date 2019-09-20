<?php
/**
 * @var WPAbstract $this
 * @var WPAbstract self
 */
$data = $this->getViewData();

use Smartling\Helpers\HtmlTagGeneratorHelper; ?>
<?php
global $tag;
$needWrapper = ($tag instanceof WP_Term);
?>

<script>
    var handleRelationsManually = <?= 0 === (int)\Smartling\Services\GlobalSettingsManager::getHandleRelationsManually() ? 'false' : 'true' ?>;
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
                        <span class="active" data-action="new">New Job</span><span
                                data-action="existing">Existing Job</span>
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

                        <?php
                        /**
                         * @var BulkSubmitTableWidget $data
                         */
                        $profile     = $data['profile'];
                        $contentType = $data['contentType'];

                        $locales = $profile->getTargetLocales();
                        \Smartling\Helpers\ArrayHelper::sortLocales($locales);
                        ?>
                        <tr>
                            <th>Target Locales</th>
                            <td>
                                <div>
                                    <?= \Smartling\WP\WPAbstract::checkUncheckBlock(); ?>
                                </div>
                                <div class="locale-list">
                                    <?php

                                    $localeList = [];

                                    foreach ($locales as $locale) {
                                        /**
                                         * @var \Smartling\Settings\TargetLocale $locale
                                         */
                                        if (!$locale->isEnabled()) {
                                            continue;
                                        }

                                        $localeList[] = $locale->getBlogId();
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
                                    <script>
                                        var localeList = "<?= implode(',', $localeList)?>";
                                    </script>
                                </div>
                            </td>
                        </tr>
                        <?php if (1 === (int)\Smartling\Services\GlobalSettingsManager::getHandleRelationsManually()) : ?>
                            <tr id="relationsInfo">
                                <th>New content to be uploaded:</th>
                                <td id="relatedContent"/>
                                </td>
                            </tr>
                            <tr>
                                <th> Extra upload options</th>
                                <td>
                                    <label for="skipRelations">Skip all related content and send <strong>only</strong>
                                        current content</label>
                                    <?=
                                    HtmlTagGeneratorHelper::tag('input', '',
                                        ['id' => 'skipRelations', 'type' => 'checkbox']);
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
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
                        var cb = success;
                        cb(response);
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
                getSelecterTargetLocales: function () {
                    var locales = [];
                    var checkedLocales = $(".job-wizard .mcheck:checkbox:checked");
                    checkedLocales.each(
                        function (e) {
                            locales.push($(checkedLocales[e]).attr("data-blog-id"));
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

                    $option = "<option value=\"" + id + "\" description=\"" + description + "\" dueDate=\"" + dueDate + "\" targetLocaleIds=\"" + locales + "\">" + name + "</option>";
                    return $option;
                },
                renderJobListInDropDown: function (data) {
                    $("#jobSelect").html("");
                    data.forEach(function (job) {
                        $option = Helper.ui.renderOption(job.translationJobUid, job.jobName, job.description, job.dueDate, job.targetLocaleIds.join(","));
                        $("#jobSelect").append($option);
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
                                $($elements[0]).attr("checked", "checked");
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
                var $action = $(this).attr("data-action");
                $("div#job-tabs span").removeClass("active");
                $(this).addClass("active");
                switch ($action) {
                    case "new":
                        Helper.ui.createJobForm.show();
                        break;
                    case "existing":
                        Helper.ui.createJobForm.hide();
                        break;
                    default:
                }
            });

            $("#timezone-sm").val(moment.tz.guess());

            var timezone = $("#timezone-sm").val();

            $("#jobSelect").select2({
                placeholder: "Please select a job",
                allowClear: false
            });

            Helper.ui.displayJobList();

            $("#dueDate").datetimepicker2({
                format: "Y-m-d H:i",
                minDate: 0
            });

            var loadRelations = function (contentType, contentId) {
                var url = `${ajaxurl}?action=smartling-get-relations&id=${contentId}&content-type=${contentType}&targetBlogIds=${localeList}`;

                $.get(url, function (data) {
                    if (data.response.data) {
                        window.relationsInfo = data.response.data;
                        recalculateRelations();
                    }
                });
            };

            if (handleRelationsManually) {

                $("#skipRelations").on("change", function () {
                    if ($(this).is(":checked")) {
                        $("#relationsInfo").addClass("hidden");
                    } else {
                        $("#relationsInfo").removeClass("hidden");
                    }
                });

                var recalculateRelations = function () {
                    $("#relatedContent").html("");
                    var relations = [];
                    var missingRelations = window.relationsInfo.missingTranslatedReferences;
                    var buildRelationsHint = function (relations) {
                        var html = "";
                        for (var type in relations) {
                            html += `${type} (${relations[type]}) </br>`;
                        }
                        return html;
                    };
                    $(".job-wizard input.mcheck[type=checkbox]:checked").each(function () {
                        var blogId = this.dataset.blogId;
                        if (Object.prototype.hasOwnProperty.call(missingRelations, blogId)) {
                            for (var sysType in missingRelations[blogId]) {
                                var sysCount = missingRelations[blogId][sysType].length;
                                if (Object.prototype.hasOwnProperty.call(relations, sysType)) {
                                    relations[sysType] += sysCount;
                                } else {
                                    relations[sysType] = sysCount;
                                }
                                $("#relatedContent").html(buildRelationsHint(relations));
                            }
                        }
                    });
                };
                loadRelations(currentContent.contentType, currentContent.id, localeList);
                $(".job-wizard input.mcheck").on("click", recalculateRelations);
                $(".job-wizard a").on("click", recalculateRelations);
            }

            if (!handleRelationsManually) {
                /*
                * Use class checking method for detecting Gutenberg as defined here https://github.com/WordPress/gutenberg/issues/12200
                * This prevents conflicts with plugins that enqueue the React library when the Classic Editor is enabled.
                */
                if (document.body.classList.contains("block-editor-page")) {
                    var hasProp = function (obj, prop) {
                        return Object.prototype.hasOwnProperty.call(obj, prop);
                    };

                    var canDispatch = hasProp(window, "wp")
                        && hasProp(window.wp, "data")
                        && hasProp(window.wp.data, "dispatch");

                    $("#addToJob").on("click", function (e) {
                        e.stopPropagation();
                        e.preventDefault();

                        if (null !== document.getElementById("smartling-bulk-submit-page-content-type")) {
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

                        $("#jobSelect").select();

                        var obj = {
                            content: {
                                type: currentContent.contentType,
                                id: currentContent.id.join(",")
                            },
                            job: {
                                id: $("#jobSelect").val(),
                                name: $("input[name=\"jobName\"]").val(),
                                description: $("textarea[name=\"description-sm\"]").val(),
                                dueDate: $("input[name=\"dueDate\"]").val(),
                                timeZone: timezone,
                                authorize: ($("div.job-wizard input[type=checkbox].authorize:checked").length > 0)
                            },
                            blogs: blogs.join(",")
                        };
                        btnLockWait();
                        $.post(
                            ajaxurl + "?action=" + "smartling_upload_handler",
                            obj,
                            function (data) {
                                if (canDispatch) {
                                    switch (data.status) {
                                        case "SUCCESS":
                                            wp.data.dispatch("core/notices").createSuccessNotice("Content added to Upload queue.");
                                            break;
                                        case "FAIL":
                                            wp.data.dispatch("core/notices").createErrorNotice("Failed adding content to download queue: " + data.message);
                                            break;
                                        default:
                                            console.log(data);
                                    }
                                }
                                btnUnlockWait();
                            }
                        );
                    });
                } else {
                    $("#addToJob").on("click", function (e) {
                        e.stopPropagation();
                        e.preventDefault();
                        var jobId = $("#jobSelect").val();
                        var jobName = $("input[name=\"jobName\"]").val();
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

                        var locales = Helper.ui.getSelecterTargetLocales();

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
                        if (currentContent.id.length) {
                            $("#" + formSelector).append(createHiddenInput("smartling[ids]", currentContent.id));
                            $("#" + formSelector).append(createHiddenInput("smartling[locales]", locales));
                            $("#" + formSelector).append(createHiddenInput("smartling[jobId]", jobId));
                            $("#" + formSelector).append(createHiddenInput("smartling[jobName]", jobName));
                            $("#" + formSelector).append(createHiddenInput("smartling[jobDescription]", jobDescription));
                            $("#" + formSelector).append(createHiddenInput("smartling[jobDueDate]", jobDueDate));
                            $("#" + formSelector).append(createHiddenInput("smartling[timezone]", timezone));
                            $("#" + formSelector).append(createHiddenInput("smartling[authorize]", $(".authorize:checked").length > 0));
                            $("#" + formSelector).append(createHiddenInput("sub", "Upload"));
                        }

                        $("#" + formSelector).submit();

                        // Support for bulk submit form.
                        if (isBulkSubmitPage && currentContent.id.length) {
                            $("input[type=\"submit\"]").click();
                        }
                    });
                }
            } else {
                $("#addToJob").on("click", function (e) {
                    e.stopPropagation();
                    e.preventDefault();

                    var url = `${ajaxurl}?action=smartling-create-submissions`;

                    var blogIds = [];

                    $(".job-wizard input.mcheck[type=checkbox]:checked").each(function () {
                        blogIds.push(this.dataset.blogId);
                    });

                    var data = {
                        source: currentContent,
                        job: {
                            id: $("#jobSelect").val(),
                            name: $("input[name=\"jobName\"]").val(),
                            description: $("textarea[name=\"description-sm\"]").val(),
                            dueDate: $("input[name=\"dueDate\"]").val(),
                            timeZone: timezone,
                            authorize: ($("div.job-wizard input[type=checkbox].authorize:checked").length > 0)
                        },
                        targetBlogIds: blogIds.join(','),
                        relations: window.relationsInfo.missingTranslatedReferences
                    };

                    if ($("#skipRelations").is(":checked")) {
                        data['relations'] = [];
                    }

                    $.post(url, data, function (d) {
                        loadRelations(currentContent.contentType, currentContent.id, localeList);
                        alert(d.status);
                    });
                });
            }

            $("#createJob").on("click", function (e) {
                e.stopPropagation();
                e.preventDefault();

                var name = $("#name-sm").val();
                var description = $("#description-sm").val();
                var dueDate = $("#dueDate").val();
                var locales = [];
                var authorize = $(".authorize:checked").length > 0;

                if (!handleRelationsManually) {
                    var checkedLocales = $(".job-wizard .mcheck:checkbox:checked");

                    checkedLocales.each(
                        function (e) {
                            locales.push($(checkedLocales[e]).attr("data-blog-id"));
                        }
                    );
                } else {
                    locales = [$("#targetBlogId").val()];
                }

                locales = locales.join(",");
                $("#error-messages").html("");

                Helper.queryProxy.createJob(name, description, dueDate, locales, authorize, timezone, function (data) {
                    var $option = Helper.ui.renderOption(data.translationJobUid, data.jobName, data.description, data.dueDate, data.targetLocaleIds.join(","));
                    $("#jobSelect").append($option);
                    $("#jobSelect").val(data.translationJobUid);
                    $("#jobSelect").change();

                    $("#addToJob").click();

                }, function (data) {
                    var messages = [];
                    if (undefined !== data["global"]) {
                        messages.push(data["global"]);
                    }
                    for (var i in data) {
                        if ("global" === i) {
                            continue;
                        } else {
                            messages.push(data[i]);
                        }
                    }
                    var text = "<span>" + messages.join("</span><span>") + "</span>";
                    $("#error-messages").html(text);
                });

            });

            $("#jobSelect").on("change", function () {
                Helper.ui.localeCheckboxes.clear();
                Helper.ui.jobForm.clear();
                $("#dueDate").val($("option[value=" + $("#jobSelect").val() + "]").attr("dueDate"));
                $("#name-sm").val($("option[value=" + $("#jobSelect").val() + "]").html());
                $("#description-sm").val($("option[value=" + $("#jobSelect").val() + "]").attr("description"));
                Helper.ui.localeCheckboxes.clear();
                Helper.ui.localeCheckboxes.set($("option[value=" + $("#jobSelect").val() + "]").attr("targetlocaleids"));
            });
        });
    })(jQuery);
</script>
