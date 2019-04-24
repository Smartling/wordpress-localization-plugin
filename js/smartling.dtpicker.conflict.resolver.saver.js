(function ($) {
    /**
     * If another datetimepicker jQuery plugin already exists BEFORE our datetimepicker load, make a backup.
     */
    if ($.fn.datetimepicker) {
        window.dtpickerBackup = $.fn.datetimepicker;
    }
})(jQuery);