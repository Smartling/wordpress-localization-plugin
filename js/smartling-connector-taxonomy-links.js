/**
 * @var ajaxurl
 * @var wp
 */
jQuery(document).on('ready', function () {
    function createOption(item) {
        var option = document.createElement('option');
        option.value = item.value;
        option.label = item.label;
        return option;
    }

    var form = jQuery('#linkTaxonomyForm');
    var button = jQuery('#link');
    var loading = jQuery('#loading');

    button.on('click', function() {
        button.prop('disable', true);
        jQuery.post(ajaxurl + '?action=smartling_link_taxonomies', form.serialize(), function (data) {
            var message = 'Taxonomies linked';
            jQuery('#taxonomy').trigger('change');
            if (wp && wp.data && wp.data.dispatch) {
                wp.data.dispatch("core/notices").createSuccessNotice(message);
            } else {
                admin_notice(message, 'success');
            }
        });
        button.prop('disable', false);
    });

    jQuery('#taxonomy, #targetBlogId').on('change', function() {
        loading.show();
        form.hide();
        jQuery.post(ajaxurl + '?action=smartling_get_terms', form.serialize(), function (data) {
            var sourceIdSelect = jQuery('#sourceId');
            var targetIdSelect = jQuery('#targetId')
            sourceIdSelect.find('option').remove();
            targetIdSelect.find('option').remove();
            data.source.forEach(function(item) {
                sourceIdSelect.append(createOption(item));
            });
            data.target.forEach(function(item) {
                targetIdSelect.append(createOption(item));
            });
            loading.hide();
            form.show();
        });
    });

    jQuery('#taxonomy').trigger('change');
});
