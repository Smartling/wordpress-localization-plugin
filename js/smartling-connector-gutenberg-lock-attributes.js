let added = false;

const addSmartlingGutenbergLockAttributes = function () {
    const registerBlockTypeHookName = 'blocks.registerBlockType';
    const namespace = 'smartling/connector/lockAttributes';
    if (typeof(wp) !== 'undefined' && wp.hasOwnProperty('hooks') && wp.hooks.hasOwnProperty('hasFilter') && wp.hooks.hasFilter(registerBlockTypeHookName, namespace)) {
        added = true;
        return;
    }
    if (typeof(wp) !== 'undefined' && wp.hasOwnProperty('hooks') && wp.hooks.hasOwnProperty('addFilter')) {
        wp.hooks.addFilter(registerBlockTypeHookName, namespace, (settings) => {
            if (settings.attributes) {
                settings.attributes['smartlingLocked'] = {
                    type: 'boolean',
                };
                settings.attributes['smartlingLockedAttributes'] = {
                    type: 'string',
                };
                settings.attributes['smartlingLockId'] = {
                    type: 'string',
                };
            }
            return settings;
        });
        if (smartling.addLockIdAttributeOnSave === '1') {
            wp.hooks.addFilter('blocks.getSaveElement', namespace, (element, blockType, attributes) => {
                if (!attributes[LOCK_ID_ATTRIBUTE] || attributes[LOCK_ID_ATTRIBUTE] === '') {
                    attributes[LOCK_ID_ATTRIBUTE] = generateSmartlingLockId();
                }

                return element;
            });
        }
        added = true;
    } else {
        console.log('Smartling WordPress connector plugin is unable to add filter to wordpress hooks: no wp.hooks.addFilter.');
    }
}

const generateSmartlingLockId = () => {
    return Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 5);
}

const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

addSmartlingGutenbergLockAttributes();

if (!added) {
    document.addEventListener('DOMContentLoaded', function () {
        addSmartlingGutenbergLockAttributes();
    });
    const interval = setInterval(() => {
        addSmartlingGutenbergLockAttributes();
        if (added) {
            clearInterval(interval);
        }
    }, 1000);
}
