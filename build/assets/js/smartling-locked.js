const {createHigherOrderComponent} = wp.compose;
const {Fragment} = wp.element;
const {InspectorControls} = wp.editor;
const {PanelBody, ToggleControl} = wp.components;
const {__} = wp.i18n;

const LOCKED = 'smartlingLocked';
const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

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
                        initialOpen={true}
                    >
                        <ToggleControl
                            label={__('Locked')}
                            checked={props.attributes[LOCKED]}
                            onChange={(value) => {
                                const newAttributes = {};
                                newAttributes[LOCKED] = value;
                                props.setAttributes(newAttributes);
                            }}
                            help={props.attributes[LOCKED] ? __('Block will not get changed on translation') : __('Block will get changed on translation')}
                        />
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'sidebarWithLockingCheckbox');

wp.hooks.addFilter('editor.BlockEdit', 'smartling/with-locking-attributes', sidebarWithLockingCheckbox);
