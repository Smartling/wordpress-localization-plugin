(function ($) {
    /**
     * Rename plugin we use from datetimepicker to datetimepicker2
     */
    $.fn.datetimepicker2 = $.fn.datetimepicker;

    if (window.dtpickerBackup) {
        /**
         * If a backup exists, restore it as datetimepicker jQuery plugin
         */
        $.fn.datetimepicker = window.dtpickerBackup;
        delete window.dtpickerBackup;
    } else {
        delete $.fn.datetimepicker;
    }
})(jQuery);