(function ($) {
    'use strict';

    $(document).ready(function () {
        if ($('#change-default-locale').length > 0) {
            $('#change-default-locale').on('click', function (e) {
                e.preventDefault();
                $('#default-locales').slideToggle('fast');
            });
            $('#smartling-configuration-profile-form').validate()
        }
        ;
        $('a.toggleExpert').on('click', function (e) {
            $('.toggleExpert').removeClass('hidden');
            $('a.toggleExpert').addClass('hidden');
        });


        $('a.saveExpertSkip').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            var data = {
                'action': 'smartling_expert_global_settings_update',
                'params': {
                    'selfCheckDisabled': $('#selfCheckDisabled').val(),
                    'disableLogging': $('#disableLogging').val(),
                    'loggingPath': $('#loggingPath').val(),
                    'pageSize': $('#pageSize').val(),
                    'disableDBLookup': $('#disableDBLookup').val()
                }
            };

            $.post($(this).attr('actionUrl'), data, function (response) {
                location.reload();
            });
        });

        $('#resetLogPath').on('click', function(e){
            e.stopPropagation();
            e.preventDefault();

            $('#loggingPath').attr('value',($(this).attr('data-path')));

        });

        $('#resetPageSize').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            $('#pageSize').attr('value', ($(this).attr('data-default')));
        });
    });
})(jQuery);