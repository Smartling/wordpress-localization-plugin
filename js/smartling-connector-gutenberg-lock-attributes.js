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
        if (smartling.addLockIdAttributeOnSave === '1') {
            wp.hooks.addFilter('blocks.getBlockAttributes', 'smartling/get-save-content/attributes', (attributes) => {
                if (!attributes[LOCK_ID_ATTRIBUTE] || attributes[LOCK_ID_ATTRIBUTE] === '') {
                    attributes[LOCK_ID_ATTRIBUTE] = generateSmartlingLockId();
                }

                return attributes;
            });
        }
        return true;
    } else {
        console.log('Smartling WordPress connector plugin is unable to add filter to wordpress hooks: no wp.hooks.addFilter.');
        return false;
    }
}

const generateSmartlingLockId = () => {
    return Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 5);
}

const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

const interval = setInterval(() => {
    if (addSmartlingGutenbergLockAttributes()) {
        clearInterval(interval);
    }
}, 1000);
