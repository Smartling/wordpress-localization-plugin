const {addFilter} = wp.hooks;

const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

const generateSmartlingLockId = () => {
    return Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 5);
}

const addLockAttributes = (settings) => {
    if (settings.attributes) {
        settings.attributes[LOCK_ID_ATTRIBUTE] = {
            type: 'string',
        };
    }

    return settings;
};

addFilter('blocks.registerBlockType', 'smartling/attribute/lockAttributes', addLockAttributes);

const getblockattributes = (attributes) => {
    if (!attributes[LOCK_ID_ATTRIBUTE] || attributes[LOCK_ID_ATTRIBUTE] === '') {
        attributes[LOCK_ID_ATTRIBUTE] = generateSmartlingLockId();
    }

    return attributes;
}

addFilter('blocks.getBlockAttributes', 'smartling/get-save-content/attributes', getblockattributes);
