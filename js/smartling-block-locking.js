const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

const generateSmartlingLockId = () => {
    return Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 5);
}

wp.hooks.addFilter('blocks.getBlockAttributes', 'smartling/get-save-content/attributes', (attributes) => {
    if (!attributes[LOCK_ID_ATTRIBUTE] || attributes[LOCK_ID_ATTRIBUTE] === '') {
        attributes[LOCK_ID_ATTRIBUTE] = generateSmartlingLockId();
    }

    return attributes;
});
