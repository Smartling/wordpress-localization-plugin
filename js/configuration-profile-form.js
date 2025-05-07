(function ($) {
    'use strict';

    $(() => {

        const changeDefaultLocale = $('#change-default-locale');
        if (changeDefaultLocale.length > 0) {
            changeDefaultLocale.on('click', function (e) {
                e.preventDefault();
                $('#default-locales').slideToggle('fast');
            });
            $('#smartling-configuration-profile-form').validate()
        }
        $('a.toggleExpert').on('click', function () {
            $('.toggleExpert').removeClass('hidden');
            $('a.toggleExpert').addClass('hidden');
        });

        $('a.saveExpertSkip').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            const data = {
                'action': 'smartling_expert_global_settings_update',
                'params': {
                    'smartling_add_slashes_before_saving_content': $('#smartling_add_slashes_before_saving_content').val(),
                    'smartling_add_slashes_before_saving_meta': $('#smartling_add_slashes_before_saving_meta').val(),
                    'smartling_custom_directives': $('#smartling_custom_directives').val(),
                    'smartling_remove_acf_parse_save_blocks_filter': $('#smartling_remove_acf_parse_save_blocks_filter').val(),
                    'smartling_target_post_fire_after_hooks': $('#smartling_target_post_fire_after_hooks').val(),
                    'selfCheckDisabled': $('#selfCheckDisabled').val(),
                    'disableLogging': $('#disableLogging').val(),
                    'loggingPath': $('#loggingPath').val(),
                    'pageSize': $('#pageSize').val(),
                    'loggingCustomization': $('#loggingCustomization').val(),
                    'smartling_frontend_generate_lock_ids': $('#smartling_frontend_generate_lock_ids').val(),
                    'smartling_related_content_select_state': $('#smartling_related_content_select_state').val(),
                    'enableFilterUI': $('#enableFilterUI').val()
                }
            };

            $.post($(this).attr('actionUrl'), data, function () {
                location.reload();
            });
        });

        $('#resetLogPath').on('click', function(e){
            e.stopPropagation();
            e.preventDefault();

            $('#loggingPath').val($(this).attr('data-path'));

        });

        $('#resetLoggingCustomization').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();

            $('#loggingCustomization').val($('#defaultLoggingCustomizations').text());
        });

        $('#resetPageSize').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            $('#pageSize').val($(this).attr('data-default'));
        });

        $('#resetHandleRelationsManually').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            $('#handleRelationsManually').val($(this).attr('data-default'));
        });

        $('#resetGenerateLockIds').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            $('#smartling_frontend_generate_lock_ids').val($(this).attr('data-default'));
        });

        $('#resetRelatedContentSelect').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            $('#smartling_related_content_select_state').val($(this).attr('data-default'));
        });
    });
})(jQuery);
