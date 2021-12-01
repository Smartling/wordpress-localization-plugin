const addSmartlingGutenbergLockAttributes = function () {
    if (wp && wp.hasOwnProperty('hooks') && wp.hooks.hasOwnProperty('addFilter')) {
        wp.hooks.addFilter('blocks.registerBlockType', 'smartling/lockAttributes', (settings) => {
            if (settings.attributes) {
                settings.attributes['smartlingLocked'] = {
                    type: 'boolean',
                }
                settings.attributes['smartlingLockId'] = {
                    type: 'string',
                }
            }
            return settings;
        });
    } else {
        console.error('Smartling WordPress connector plugin is unable to add filter to wordpress hooks: no wp.hooks.addFilter.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    addSmartlingGutenbergLockAttributes();
});
