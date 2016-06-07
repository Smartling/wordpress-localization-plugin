(function ($) {
    'use strict';

    $(document).ready(function () {
        if ($('#change-default-locale').length > 0) {
            $('#change-default-locale').on('click', function (e) {
                e.preventDefault();
                $('#default-locales').slideToggle('fast');
            });
            $('#smartling-configuration-profile-form').validate();
            $('a.toggleExpert').on('click', function (e) {
                $('.toggleExpert').removeClass('hidden');
                $('a.toggleExpert').addClass('hidden');
            });
        }
    });
})(jQuery);