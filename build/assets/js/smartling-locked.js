const {createHigherOrderComponent} = wp.compose;
const {Fragment} = wp.element;
const {InspectorControls} = wp.editor;
const {PanelBody, CheckboxControl} = wp.components;
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
                        <CheckboxControl
                            label={__('Locked')}
                            checked={props.attributes[LOCKED]}
                            onChange={(value) => {
                                const newAttributes = {};
                                newAttributes[LOCKED] = value;
                                props.setAttributes(newAttributes);
                            }}
                            help={props.attributes[LOCKED] ? __('Content will not change on translation') : __('Content will change on translation')}
                        />
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'sidebarWithLockingCheckbox');

wp.hooks.addFilter('editor.BlockEdit', 'smartling/with-locking-attributes', sidebarWithLockingCheckbox);
