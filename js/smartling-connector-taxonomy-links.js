/**
 * @var ajaxurl
 * @var submissions
 * @var wp
 */
jQuery(document).on('ready', function () {
    function createOption(item) {
        const option = document.createElement('option');
        option.value = item.value;
        option.label = item.label;
        return option;
    }

    const button = jQuery('#link');
    const sourceIdSelect = jQuery('#sourceId');
    const taxonomySelect = jQuery('#taxonomy');

    button.on('click', function () {
        button.prop('disable', true);
        jQuery.post(ajaxurl + '?action=smartling_link_taxonomies', jQuery('#linkTaxonomyForm').serialize(), function (data) {
            const success = data.success;
            if (success) {
                const message = 'Taxonomies linked';
                submissions = data.submissions;
                if (wp && wp.data && wp.data.dispatch) {
                    try {
                        wp.data.dispatch('core/notices').createSuccessNotice(message);
                    } catch (e) {
                        admin_notice(message, 'success')
                        console.log(e);
                    }
                } else {
                    admin_notice(message, 'success');
                }
            } else {
                if (wp && wp.data && wp.data.dispatch) {
                    try {
                        wp.data.dispatch('core/notices').createErrorNotice(data.data);
                    } catch (e) {
                        admin_notice(message, 'error');
                        console.log(e);
                    }
                } else {
                    admin_notice(data.data, 'error');
                }
            }
        });
        button.prop('disable', false);
    });

    sourceIdSelect.on('change', function () {
        Object.keys(terms).forEach(function (blogId) {
            const sourceId = sourceIdSelect.val();
            const targetSelect = jQuery('#targetId_' + blogId);
            let targetId = 0;
            if (submissions[sourceId] && submissions[sourceId][blogId]) {
                targetId = submissions[sourceId][blogId];
            }
            targetSelect.val(targetId);
        });
    });

    taxonomySelect.on('change', function () {
        const taxonomy = taxonomySelect.val();
        sourceIdSelect.find('option').remove();
        try {
            terms[jQuery('#sourceBlogId').val()][taxonomy].forEach(function (item) {
                sourceIdSelect.append(createOption(item));
            });
        } catch (e) {
            sourceIdSelect.append(createOption({value: 0, label: 'No terms'}));
        }
        Object.keys(terms).forEach(function (blogId) {
            const targetSelect = jQuery('#targetId_' + blogId);
            targetSelect.find('option').remove();
            targetSelect.append(createOption({value: 0, label: 'Not linked in Smartling connector'}));
            try {
                terms[blogId][taxonomy].forEach(function (item) {
                    targetSelect.append(createOption(item));
                });
            } catch (e) {
                console.debug(`No terms for ${taxonomy} found in target blog ${blogId}`);
            }
        });
        sourceIdSelect.trigger('change');
    });

    taxonomySelect.trigger('change');
});
