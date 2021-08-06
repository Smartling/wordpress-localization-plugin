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

wp.hooks.addFilter('editor.BlockEdit', 'smartling/get-edit-attributes', wp.compose.createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
        if (!props.attributes.hasOwnProperty(LOCK_ID_ATTRIBUTE)) {
            const newProps = {};
            newProps[LOCK_ID_ATTRIBUTE] = generateSmartlingLockId();
            props.setAttributes(newProps);
        }
        return wp.element.createElement(
            wp.element.Fragment,
            {},
            wp.element.createElement(BlockEdit, props),
            wp.element.createElement(wp.blockEditor.InspectorControls, {}),
        );
    };
}, 'addSmartlingLockId'));
