(function ($) {
    $(document).ready(function () {
        $('#bulk-submit-main').on('submit', function () {
            var validLocales = $('input.mcheck[type=checkbox]:checked').length > 0;
            var validContent = $('input.bulkaction[type=checkbox]:checked').length > 0;
            var messages = [];
            if (!validLocales) {
                messages.push('At least one target locale should be selected');
            }
            if (!validContent) {
                messages.push('At least one content entity should be selected for translation');
            }
            if (validLocales && validContent) {
                $('#error-messages').html('');
                return true;
            } else {
                var errorString = '<span>' + messages.join('</span><span>') + '</span>';
                $('#error-messages').html(errorString);
                return false;
            }
        });
    });
})(jQuery);