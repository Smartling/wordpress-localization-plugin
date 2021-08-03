wp.hooks.addFilter('blocks.registerBlockType', 'smartling/attributes/lockAttributes', (settings) => {
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
