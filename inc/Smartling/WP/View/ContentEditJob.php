<?php

use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\Services\BaseAjaxServiceAbstract;
use Smartling\Services\ContentRelationsHandler;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\WP\Controller\ContentEditJobController;
use Smartling\WP\Table\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;

$data = $this->viewData;
assert($this instanceof ContentEditJobController);
$profile = $data['profile'];
assert($profile instanceof ConfigurationProfileEntity);
$widgetName = 'bulk-submit-locales';

$isBulkSubmitPage = get_current_screen()?->id === 'smartling_page_smartling-bulk-submit';
global $tag;
$needWrapper = ($tag instanceof WP_Term);

$id = 0;
$baseType = 'unknown';
global $post;
if ($post instanceof WP_Post) {
    $id = $post->ID;
    $baseType = 'post';
} else {
    if ($tag instanceof WP_Term) {
        $id = $tag->term_id;
        $baseType = 'taxonomy';
    }
}

$locales = $profile->getTargetLocales();
ArrayHelper::sortLocales($locales);
$localesData = array_map(function($locale) {
    return [
        'blogId' => $locale->getBlogId(),
        'label' => $locale->getLabel(),
        'smartlingLocale' => $locale->getSmartlingLocale(),
        'enabled' => $locale->isEnabled()
    ];
}, array_filter($locales, fn($l) => $l->isEnabled()));

if (!$isBulkSubmitPage) : ?>
<?php if ($needWrapper) : ?>
<div class="postbox-container" style="width: 550px">
    <div id="panel-box" class="postbox hndle"><h2><span>Translate content</span></h2>
        <div class="inside">
<?php endif; ?>
            <div id="smartling-app"
                 data-bulk-submit="false"
                 data-content-type="<?= $data['contentType'] ?? $baseType ?>"
                 data-content-id="<?= $id ?>"
                 data-locales='<?= htmlspecialchars(json_encode(array_values($localesData), JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                 data-ajax-url="<?= admin_url('admin-ajax.php') ?>"
                 data-admin-url="<?= admin_url('admin-ajax.php') ?>"></div>
<?php if ($needWrapper) : ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div style="display:none;">
<style>
.relations-list {
    max-height: 200px;
    overflow-y: auto;
}
.relation-item {
    margin: 5px 0;
}
.relation-item label {
    display: block;
    cursor: pointer;
}
</style>
<?php
?>

<script>
    const isBulkSubmitPage = <?= $isBulkSubmitPage ? 'true' : 'false'?>;
    let l1Relations = {references: []};
    let l2Relations = {references: []};
    let globalButton;
</script>

<?php if ($needWrapper && false) : ?>
<div class="postbox-container" style="width: 550px">
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
                                    <?= WPAbstract::checkUncheckBlock($widgetName) ?>
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
                                                $widgetName,
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
                        <tr>
                            <th>Related content</th>
                            <td>
                                <?= HtmlTagGeneratorHelper::tag(
                                    'select',
                                    HtmlTagGeneratorHelper::renderSelectOptions(
                                        GlobalSettingsManager::getRelatedContentSelectState(),
                                        [
                                            0 => 'Don\'t send  related content',
                                            1 => 'Send related content one level deep',
                                            2 => 'Send related content two levels deep',
                                        ]
                                    ),
                                    [
                                        'id' => 'depth',
                                        'name' => 'depth',
                                    ],
                                )?>
                            </td>
                        </tr>
                        <tr id="relationsInfo">
                            <th>Related content to be uploaded:</th>
                            <td id="relatedContent">
                            </td>
                        </tr>
                        <tr>
                            <th class="center" colspan="2">
                                <div id="error-messages"></div>
                                <div id="progress-indicator" class="hidden" style="margin: 10px 0;">
                                    <div style="background: #f0f0f0; border-radius: 4px; overflow: hidden; height: 20px;">
                                        <div id="progress-bar" style="background: #2271b1; height: 100%; width: 0; transition: width 0.3s;"></div>
                                    </div>
                                    <div id="progress-text" style="margin-top: 5px; font-size: 12px;"></div>
                                </div>
                                <div id="loader-image" class="hidden"><span class="loader"></span></div>
                                <button class="button button-primary components-button is-primary" id="createJob"
                                        title="Create a new job and add content into it">Create Job
                                </button>
                                <button class="button button-primary components-button is-primary hidden" id="addToJob"
                                        title="Add content into your chosen job">Add to selected Job
                                </button>
                                <button class="button button-primary components-button is-primary hidden" id="cloneButton">Clone</button>
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
                baseEndpoint: '<?= admin_url('admin-ajax.php')?>?action=<?= ContentEditJobController::SMARTLING_JOB_API_PROXY?>',
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

        const busyClass = 'is-busy';
        const buttonTexts = {};

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

            const depthSelector = $('#depth');
            const recalculateRelations = function recalculateRelations() {
                let allRelations = [];
                const relatedContent = $("#relatedContent");
                relatedContent.html("");
                const depth = depthSelector.val();
                switch (depth) {
                    case "0":
                        return;
                    case "1":
                        allRelations = l1Relations.references;
                        break;
                    case "2":
                        allRelations = [...(l1Relations.references), ...(l2Relations.references)];
                        const seen = new Set();
                        allRelations = allRelations.filter(rel => {
                            const key = `${rel.contentType}-${rel.id}`;
                            if (seen.has(key)) return false;
                            seen.add(key);
                            return true;
                        });
                }

                if (allRelations.length > 0) {
                    let html = '<div class="relations-list">';
                    allRelations.forEach(relation => {
                        const checkboxId = `relation-${relation.contentType}-${relation.id}`;
                        const isChecked = relation.status !== '<?= SubmissionEntity::SUBMISSION_STATUS_COMPLETED ?>' ? 'checked' : '';
                        const titleLink = relation.url ? `<a href="${relation.url}">${relation.title || 'Untitled'}</a>` : (relation.title || 'Untitled');
                        const thumbnail = relation.contentType === 'attachment' && relation.thumbnailUrl ?
                            ` <img src="${relation.thumbnailUrl}" alt="Preview" style="width:30px;height:30px;object-fit:cover;vertical-align:middle;margin-left:5px;">` : '';
                        html += `<div class="relation-item">
                            <label>
                                <input type="checkbox" id="${checkboxId}" class="relation-checkbox"
                                       data-content-type="${relation.contentType}"
                                       data-id="${relation.id}"
                                       data-status="${relation.status}" ${isChecked}>
                                ${relation.contentType} #${relation.id} (${relation.status}) - ${titleLink}${thumbnail}
                            </label>
                        </div>`;
                    });
                    html += '</div>';
                    relatedContent.html(html);
                }
            };

            const loadRelations = function loadRelations(contentType, contentId, level = 1) {
                const url = `${ajaxurl}?action=<?= ContentRelationsHandler::ACTION_NAME?>&id=${contentId}&content-type=${contentType}&targetBlogIds=${localeList}`;
                pendingRequests++;
                totalRequests++;
                $('#progress-indicator').removeClass('hidden');
                updateProgress();
                $('#createJob, #addToJob').prop('disabled', true).addClass(busyClass);

                $.get(url, function loadData(data) {
                    if (data.response.data) {
                        switch (level) {
                            case 1:
                                const newReferences = data.response.data.references;
                                const existingKeys = new Set(l1Relations.references.map(r => `${r.contentType}-${r.id}`));
                                const uniqueReferences = newReferences.filter(r => !existingKeys.has(`${r.contentType}-${r.id}`));
                                l1Relations.references = l1Relations.references.concat(uniqueReferences);
                                window.relationsInfo = data.response.data;
                                break;
                            case 2:
                                const references = data.response.data.references;
                                const existingL2Keys = new Set(l2Relations.references.map(r => `${r.contentType}-${r.id}`));
                                const uniqueL2References = references.filter(r => !existingL2Keys.has(`${r.contentType}-${r.id}`));
                                l2Relations.references = l2Relations.references.concat(uniqueL2References);
                                break;
                        }

                        recalculateRelations();
                    }
                }).always(() => {
                    pendingRequests--;
                    updateProgress();
                    if (pendingRequests === 0) {
                        $('#createJob, #addToJob').prop('disabled', false).removeClass(busyClass);
                    }
                });
            };

            const lockUploadWidgetButton = function lockUploadWidgetButton(event) {
                const button = $(event.target);
                if (button.hasClass(busyClass)) {
                    return false;
                }

                buttonTexts[button.id] = button.text();
                button.text('Wait...');
                button.addClass(busyClass);
                return true;
            }

            const unlockUploadWidgetButton = function unlockUploadWidgetButton(button) {
                button.removeClass(busyClass);
                button.text(buttonTexts[button.id]);
            }

            let relationsLoaded = false;
            let level2RelationsLoaded = false;
            let pendingRequests = 0;
            let totalRequests = 0;

            const updateProgress = function() {
                const progress = totalRequests > 0 ? ((totalRequests - pendingRequests) / totalRequests) * 100 : 0;
                $('#progress-bar').css('width', progress + '%');
                $('#progress-text').text(`Loading relations: ${totalRequests - pendingRequests} of ${totalRequests} completed`);

                if (pendingRequests === 0 && totalRequests > 0) {
                    setTimeout(() => $('#progress-indicator').addClass('hidden'), 1000);
                }
            };

            const loadRelationsOnce = function() {
                if (!relationsLoaded) {
                    relationsLoaded = true;
                    if (isBulkSubmitPage) {
                        $("input.bulkaction[type=checkbox]:checked").each(function () {
                            var parts = $(this).attr("id").split("-");
                            var id = parseInt(parts.shift());
                            var contentType = parts.join("-");
                            loadRelations(contentType, id, 1);
                        });
                    } else {
                        loadRelations(currentContent.contentType, currentContent.id, 1);
                    }
                }

                const depth = depthSelector.val();
                if (depth === "2" && !level2RelationsLoaded) {
                    level2RelationsLoaded = true;
                    for (const relation of l1Relations.references) {
                        loadRelations(relation.contentType, relation.id, 2);
                    }
                }
            };

            if (depthSelector.val() !== "0") {
                loadRelationsOnce();
            }

            $('.job-wizard input.mcheck, .job-wizard a').on('click', recalculateRelations);
            depthSelector.on('change', function() {
                if ($(this).val() !== "0") {
                    loadRelationsOnce();
                }
                recalculateRelations();
            });

            if (isBulkSubmitPage) {
                $(document).on('change', 'input.bulkaction[type=checkbox]', function() {
                    relationsLoaded = false;
                    level2RelationsLoaded = false;
                    pendingRequests = 0;
                    totalRequests = 0;
                    l1Relations = {references: []};
                    l2Relations = {references: []};
                    depthSelector.val('0');
                    $("#relatedContent").html("");
                    $('#progress-indicator').addClass('hidden');
                    $('#createJob, #addToJob').prop('disabled', false).removeClass(busyClass);
                });
            }
            var hasProp = function (obj, prop) {
                return Object.prototype.hasOwnProperty.call(obj, prop);
            };

            var canDispatch = hasProp(window, "wp")
                && hasProp(window.wp, "data")
                && hasProp(window.wp.data, "dispatch")
            ;

            $("#addToJob, #cloneButton").on("click", function (e) {
                e.stopPropagation();
                e.preventDefault();
                const btn = $(e.target);
                if (!lockUploadWidgetButton(e)) {
                    return;
                }
                $('#error-messages').hide();

                var url = `${ajaxurl}?action=<?= ContentRelationsHandler::ACTION_NAME_CREATE_SUBMISSIONS?>`;

                var blogIds = [];

                $(".job-wizard input.mcheck[type=checkbox]:checked").each(function () {
                    blogIds.push(this.dataset.blogId);
                });

                var data = {
                    formAction: e.target.id === 'cloneButton' ? '<?= ContentRelationsHandler::FORM_ACTION_CLONE?>' : '<?= ContentRelationsHandler::FORM_ACTION_UPLOAD?>',
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

                const prepareRequest = () => {
                    const selectedRelations = {};

                    const targetBlogIds = [];
                    $(".job-wizard input.mcheck[type=checkbox]:checked").each(function () {
                        targetBlogIds.push(this.dataset.blogId);
                    });

                    $(".relation-checkbox:checked").each(function () {
                        const contentType = this.dataset.contentType;
                        const id = parseInt(this.dataset.id);

                        targetBlogIds.forEach(blogId => {
                            if (!selectedRelations[blogId]) {
                                selectedRelations[blogId] = {};
                            }
                            if (!selectedRelations[blogId][contentType]) {
                                selectedRelations[blogId][contentType] = [];
                            }
                            if (!selectedRelations[blogId][contentType].includes(id)) {
                                selectedRelations[blogId][contentType].push(id);
                            }
                        });
                    });

                    return selectedRelations;
                };

                data.relations = prepareRequest();

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
                        case "<?= BaseAjaxServiceAbstract::RESPONSE_SUCCESS ?>":
                            cssStyle='success';
                            break;
                        case "<?= BaseAjaxServiceAbstract::RESPONSE_FAILED ?>":
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
                                case "<?= BaseAjaxServiceAbstract::RESPONSE_SUCCESS ?>":
                                    wp.data.dispatch("core/notices").createSuccessNotice(message);
                                    break;
                                case "<?= BaseAjaxServiceAbstract::RESPONSE_FAILED ?>":
                                    wp.data.dispatch("core/notices").createErrorNotice(message);
                                    break;
                                default:
                                    console.log(data);
                            }
                        } else {
                            if (style === '<?= BaseAjaxServiceAbstract::RESPONSE_FAILED ?>') {
                                $('#error-messages').html(message).show();
                            }
                        }
                    };
                }

                var message = "Failed adding content to upload queue.";
                $.post(url, data, function (d) {
                    switch (d.status) {
                        case "<?= BaseAjaxServiceAbstract::RESPONSE_SUCCESS ?>":
                            uiShowMessage(d.status, "Content successfully added to upload queue.");
                            break;
                        case "<?= BaseAjaxServiceAbstract::RESPONSE_FAILED ?>":
                        default:
                            uiShowMessage("<?= BaseAjaxServiceAbstract::RESPONSE_FAILED ?>", message);
                            break;
                    }
                }).fail(function (e) {
                    if (e.responseJSON && e.responseJSON.response && e.responseJSON.response.message) {
                        message = e.responseJSON.response.message;
                    }
                    uiShowMessage("<?= BaseAjaxServiceAbstract::RESPONSE_FAILED ?>", message);
                })
                    .then(() => unlockUploadWidgetButton(btn));
            });

            $("#createJob").on("click", function (e) {
                e.stopPropagation();
                e.preventDefault();
                const btn = $(e.target);
                if (!lockUploadWidgetButton(e)) {
                    return;
                }

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
                    unlockUploadWidgetButton(btn);
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
                    unlockUploadWidgetButton(btn);
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
