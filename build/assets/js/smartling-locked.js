const {createHigherOrderComponent} = wp.compose;
const {Fragment} = wp.element;
const {InspectorControls} = wp.editor;
const {PanelBody, CheckboxControl} = wp.components;
const {addFilter} = wp.hooks;
const {__} = wp.i18n;

const LOCKED = 'smartlingLocked';
const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

const addLockAttributes = (settings) => {
    if (settings.attributes) {
        settings.attributes[LOCKED] = {
            type: 'boolean',
        }
        settings.attributes[LOCK_ID_ATTRIBUTE] = {
            type: 'string',
        }
    }

    return settings;
};

addFilter('blocks.registerBlockType', 'smartling/attribute/lockAttributes', addLockAttributes);

const sidebarWithLockingCheckbox = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        if (props.attributes) {
            if (!props.attributes[LOCK_ID_ATTRIBUTE]) {
                return (<Fragment><BlockEdit {...props} /></Fragment>);
            }
        }

        return (
            <Fragment>
                <BlockEdit {...props} />
                <InspectorControls>
                    <PanelBody
                        title={__('Smartling lock')}
                        initialOpen={false}
                    >
                        <CheckboxControl
                            label={__('Locked')}
                            checked={props.attributes[LOCKED]}
                            onChange={(value) => {
                                const newAttributes = {};
                                newAttributes[LOCKED] = value;
                                props.setAttributes(newAttributes);
                            }}
                        />
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'sidebarWithLockingCheckbox');

addFilter('editor.BlockEdit', 'smartling/with-locking-attributes', sidebarWithLockingCheckbox);
