const addSmartlingGutenbergLockAttributes = function () {
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
}

addSmartlingGutenbergLockAttributes();
