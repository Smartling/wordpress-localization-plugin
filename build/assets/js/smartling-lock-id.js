import assign from 'lodash.assign';

const {createHigherOrderComponent} = wp.compose;
const {Fragment} = wp.element;
const {InspectorControls} = wp.editor;
const {PanelBody, CheckboxControl} = wp.components;
const {addFilter} = wp.hooks;
const {__} = wp.i18n;

const LOCKED = 'smartlingLocked';
const LOCK_ID_ATTRIBUTE = 'smartlingLockId';

const generateSmartlingLockId = () => {
    return Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 5);
}

const addLockAttributes = (settings) => {
    const newSettings = {}
    newSettings[LOCKED] = {
        type: 'boolean',
    };
    newSettings[LOCK_ID_ATTRIBUTE] = {
        type: 'string',
    };
    // Use Lodash's assign to gracefully handle if attributes are undefined
    settings.attributes = assign(settings.attributes, newSettings);

    return settings;
};

addFilter('blocks.registerBlockType', 'smartling/attribute/lockAttributes', addLockAttributes);

const sidebarWithLockingCheckbox = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        console.log('wla', props);
        if (props.attributes) {
            if (!props.attributes[LOCK_ID_ATTRIBUTE]) {
                return;
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
                            value={props.attributes[LOCKED]}
                            onChange={(value) => {
                                const newAttributes = {};
                                newAttributes[LOCKED] = value;
                                props.setAttributes(newAttributes);
                                console.log('nv');
                            }}
                        />
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'sidebarWithLockingCheckbox');

addFilter('editor.BlockEdit', 'smartling/with-locking-attributes', sidebarWithLockingCheckbox);

const extraprops = (saveElementProps, blockType, attributes) => {
    if (!saveElementProps.attributes) {
        return saveElementProps;
    }
    if (!saveElementProps.attributes[LOCK_ID_ATTRIBUTE] || saveElementProps.attributes[LOCK_ID_ATTRIBUTE] === '') {
        // Use Lodash's assign to gracefully handle if attributes are undefined
        assign(saveElementProps, {attributes: {'data-smartling-lock-id': ''}});
    }

    return saveElementProps;
};

addFilter('blocks.getSaveContent.extraProps', 'smartling/get-save-content/extra-props', extraprops);

const saveelement = (element, blockType, attributes) => {
    /*console.log('called getSaveElement', element, blockType, attributes);
    if (!element.props[LOCK_ID_ATTRIBUTE] || element.props[LOCK_ID_ATTRIBUTE] === '') {
        console.log('no id, adding save');
        assign(element, {props: {LOCK_ID_ATTRIBUTE: generateSmartlingLockId()}});
    }
    console.log('returning', element);*/
    return element;
}

addFilter('blocks.getSaveElement', 'smartling/get-save-content/element', saveelement);

const getblockattributes = (attributes, blockType, body) => {
    if (!attributes[LOCK_ID_ATTRIBUTE] || attributes[LOCK_ID_ATTRIBUTE] === '') {
        attributes[LOCK_ID_ATTRIBUTE] = generateSmartlingLockId();
    }

    return attributes;
}

addFilter('blocks.getBlockAttributes', 'smartling/get-save-content/attributes', getblockattributes);
