/**
 * @var ajaxurl
 * @var wp
 */
var downloadSelector = "#smartling-download";
(function ($) {
    'use strict';

    var localizationOptions = {

        selectors: {
            form: '#smartling-form',
            post_widget: '#smartling-post-widget',
            submit: '#submit',
            download: downloadSelector,
            errors_container: '.display-errors',
            errors: '.display-errors .error',
            set_default_locale: '#change-default-locale',
            default_locales: '#default-locales'
        },

        fields: {
            api_key_real: 'smartling_settings[apiKey]',
            project_id: 'smartling_settings[projectId]',
            api_key: 'apiKey',
            mandatory: [
                'smartling_settings[apiUrl]',
                'smartling_settings[projectId]',
                'smartling_settings[retrievalType]',
                'smartling_settings[apiKey]'
            ]
        },

        patterns: {
            project_id: /^[\w]+$/,
            api_key: /^[\w.\-]+$/
        },

        errorsMsg: {
            api_key: 'Key length must be n chars.',
            project_id: 'Project ID length must be n chars.',
            default: function (name) {
                if (name !== undefined) {
                    return name + ' field is mandatory.';
                } else {
                    return 'The field is mandatory.';
                }
            }
        },


        init: function () {
            $(this.selectors.form).on('click', this.selectors.submit, $.proxy(this.onSubmit, this));
            $(this.selectors.form + ',' + this.selectors.post_widget).on('click', 'input:checkbox', $.proxy(this.setCheckboxValue, this));
            $(this.selectors.set_default_locale).on('click', $.proxy(this.onChangeDefaultLocale, this));
        },

        createErrorTemplate: function (msg) {
            return '<div class="error settings-error"><p><strong>' + msg + '</strong></p></div>';
        },

        displayError: function (msg) {
            var tmpl = this.createErrorTemplate(msg);

            this.renderTo(this.selectors.errors_container, tmpl);
        },

        getFieldValue: function (name) {
            var input = this.getInputByName(name);

            return input.val();
        },

        getFieldName: function (name) {
            var input = this.getInputByName(name);

            return input.closest('tr').find('th').text();
        },
        getInputByName: function (name) {
            var selector = 'input[name="' + name + '"]';

            return $(selector);
        },
        hideErrors: function () {
            $(this.selectors.errors).remove();
        },
        onSubmit: function () {
            this.hideErrors();

            return this.validateFields($(this.selectors.form).serializeArray());
        },

        onChangeDefaultLocale: function (e) {
            e.preventDefault();

            $(this.selectors.default_locales).slideToggle('fast');
        },

        setFieldValue: function (name, val) {
            var input = this.getInputByName(name);

            input.val(val);
        },

        setCheckboxValue: function (e) {
            var
                checkbox = $(e.target),
                checkbox_real = $(checkbox).siblings('input:hidden').get(0);

            if (checkbox.is(':checked')) {
                $(checkbox_real).val('true');
            } else {
                $(checkbox_real).val('false');
            }
        },

        renderTo: function (place, template) {

            $(template).appendTo(place);
        },

        validateFields: function (fields) {
            var
                new_key = this.getFieldValue(this.fields.api_key),
                real_key = this.getFieldValue(this.fields.api_key_real),
                project_id = this.getFieldValue(this.fields.project_id),
                valid = true,
                self = this;

            $.each(fields, function (index, val) {

                if (val['name'] == self.fields.api_key_real) {

                    if (self.patterns.api_key.test(new_key)) {

                        self.setFieldValue(self.fields.api_key_real, new_key);

                    } else if (!self.patterns.api_key.test(new_key) && new_key !== '') {

                        self.displayError(self.errorsMsg.api_key);
                        valid = false;

                    } else if (new_key == '' && !real_key.length) {

                        self.displayError(self.errorsMsg.api_key);
                        valid = false;
                    }

                } else if (val['name'] == self.fields.project_id) {

                    if (self.patterns.project_id.test(project_id)) {

                        self.setFieldValue(self.fields.project_id, project_id);

                    } else if (project_id !== '' || !self.patterns.project_id.test(project_id)) {

                        self.displayError(self.errorsMsg.project_id);
                        valid = false;

                    } else if (project_id == '') {
                        var name = self.getFieldName(self.fields.project_id);

                        self.displayError(self.errorsMsg.default(name));
                    }

                } else if ($.inArray(val['name'], self.fields.mandatory) > -1) {

                    var
                        name = self.getFieldName(val['name']),
                        input = self.getInputByName(val['name']),
                        type = input.attr('type');

                    if (type == 'checkbox' && !input.is(':checked')) {

                        valid = false;
                        self.displayError(self.errorsMsg.default(name));

                    } else if (input.val() == '' || input.val() == 'false') {

                        valid = false;
                        self.displayError(self.errorsMsg.default(name));
                    }

                }
            });

            return valid;
        }
    };

    $(function () {
        if ($(localizationOptions.selectors.form).length > 0) {
            localizationOptions.init();
        }
        if ($(localizationOptions.selectors.post_widget).length > 0) {
            $(localizationOptions.selectors.download).on("click", function () {
                ajaxDownload();
            });
        }
    });
})(jQuery);

// @see \Smartling\WP\WPAbstract::checkUncheckBlock()
// noinspection JSUnusedGlobalSymbols
function bulkCheck(className, action) {
    jQuery.each(jQuery('.' + className), function (i, e) {
        this.checked = 'check' === action;
    });
}

jQuery(document).ready(function () {
    jQuery('.checkall').on('click', function (e) {
        e.stopPropagation();
        jQuery('.bulkaction').prop("checked", jQuery(e.target).is(':checked'));
    });

    jQuery('#sent-to-smartling-bulk').on('click', function (e) {
        jQuery('#ct').val(jQuery('#smartling-bulk-submit-page-content-type').val());
    });

    jQuery('.ajaxcall').click(function (e) {
        e.stopPropagation();
        e.preventDefault();
        var $url = jQuery(this).attr('href');

        jQuery(this).parent().html('<strong>Running, please wait...</strong>');

        /**
         * reload page in 60 seconds if timeout
         */
        var timeout = setTimeout('location.reload();', 60 * 1000);
        jQuery.getJSON($url, function (data) {
            location.reload();
        });
    })

});

function ajaxDownload() {
    var $ = jQuery;
    var message = '';
    var type = '';
    var wp5an = "components-button editor-post-publish-button is-button is-default is-primary is-large is-busy";
    var btn = $(downloadSelector);
    var btnLockWait = function () {
        if (btn.hasClass(wp5an)) {
            return false;
        }
        $(btn).addClass(wp5an);
        $(btn).val("Wait...");
        return true;
    };
    var btnUnlockWait = function () {
        $(btn).removeClass(wp5an);
        $(btn).val("Download");
    };

    var submissionIds = [];
    var checkedTargetLocales = $(".smtPostWidget-row input.mcheck:checked");

    if (0 < checkedTargetLocales.length) {
        if (!btnLockWait()) {
            return;
        }
        for (var i = 0; i < checkedTargetLocales.length; i++) {
            var submissionId = $(checkedTargetLocales[i]).attr("data-submission-id");
            submissionIds = submissionIds.concat([submissionId]);
        }
        $.post(
            ajaxurl + "?action=" + "smartling_force_download_handler",
            {
                submissionIds: submissionIds.join(",")
            },
            function (data) {
                switch (data.status) {
                    case "SUCCESS":
                        message = "Translations downloaded.";
                        type = "success";
                        break;
                    case "FAIL":
                        message = "Translations download failed.";
                        type = "error";
                        if (data.message) {
                            message += "\n" + data.message;
                        }
                        break;
                }
                if (wp && wp.data && wp.data.dispatch) {
                    var dispatch = wp.data.dispatch("core/notices");
                    switch (type) {
                        case "success":
                            try {
                                dispatch.createSuccessNotice(message);
                            } catch (e) {
                                admin_notice(message, type);
                            }
                            break;
                        case "error":
                            try {
                                dispatch.createErrorNotice(message);
                            } catch (e) {
                                admin_notice(message, type);
                            }
                            break;
                    }
                } else {
                    admin_notice(message, type);
                }
                btnUnlockWait();
            }
        );
    }
}

function admin_notice(message, type) {
    var div = document.createElement("div");
    div.classList.add("notice", "notice-" + type);
    div.style.position = "relative";
    var p = document.createElement("p");
    p.appendChild(document.createTextNode(message));
    div.appendChild(p);
    var dismissButton = document.createElement("button");
    dismissButton.setAttribute("type", "button");
    dismissButton.setAttribute("title", "dismiss");
    dismissButton.classList.add("notice-dismiss");
    div.appendChild(dismissButton);
    var h1 = document.getElementsByTagName("h1")[0];
    h1.parentNode.insertBefore(div, h1.nextSibling);
    dismissButton.addEventListener("click", function () {
        div.parentNode.removeChild(div);
    });
}

jQuery(() => {
    addSmartlingGutenbergLockAttributes();
});
