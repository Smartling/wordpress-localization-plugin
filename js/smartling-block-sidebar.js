wp.hooks.addFilter('editor.BlockEdit', 'smartling/with-locking-attributes', wp.compose.createHigherOrderComponent(function (blockEdit) {
    return function (props) {
        const LOCKED = 'smartlingLocked';
        const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

        return props.attributes && !props.attributes[LOCK_ID_ATTRIBUTE] ?
            React.createElement(
                wp.element.Fragment,
                null,
                React.createElement(blockEdit, props)
            ) :
            React.createElement(
                wp.element.Fragment,
                null,
                React.createElement(blockEdit, props),
                React.createElement(
                    wp.editor.InspectorControls,
                    null,
                    React.createElement(
                        wp.components.PanelBody,
                        {
                            title: wp.i18n.__('Smartling lock'),
                            initialOpen: true
                        },
                        React.createElement(
                            wp.components.ToggleControl,
                            {
                                label: wp.i18n.__('Locked'),
                                checked: props.attributes[LOCKED],
                                onChange: function (value) {
                                    props.setAttributes({smartlingLocked: value})
                                },
                                help: props.attributes[LOCKED] ? wp.i18n.__('Block will not get changed on translation') : wp.i18n.__('Block will get changed on translation')
                            }
                        )
                    )
                )
            )
    }
}, 'sidebarWithLockingCheckbox'));
