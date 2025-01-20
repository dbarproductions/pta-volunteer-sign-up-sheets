import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { createBlock } from '@wordpress/blocks';
import { Button } from '@wordpress/components';
import { createElement } from '@wordpress/element';

const Edit = ({ attributes, setAttributes }) => {
    const blockProps = useBlockProps();

    return (

        <div {...blockProps}>

            <ServerSideRender
                block="pta-volunteer-sus/signup-sheet"
                attributes={attributes}
            />
            <InspectorControls>
                <PanelBody title={__('Sheet Settings', 'pta-volunteer-sign-up-sheets')}>
                    <div className="pta-volunteer-signup-guidance">
                        <p>{__('This block displays all volunteer sign-up sheets by default.', 'pta-volunteer-sign-up-sheets')}</p>
                        <p>{__('To preview a specific sheet, enter its ID below. Remember to clear the ID to show all sheets again.', 'pta-volunteer-sign-up-sheets')}</p>
                    </div>
                    <TextControl
                        label={__('Sheet ID', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.id}
                        onChange={(value) => setAttributes({id: value})}
                        type="number"
                        min={1}
                    />
                    <TextControl
                        label={__('Date', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.date}
                        onChange={(value) => setAttributes({date: value})}
                    />
                    <TextControl
                        label={__('Group', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.group}
                        onChange={(value) => setAttributes({group: value})}
                    />
                    <TextControl
                        label={__('List Title', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.list_title}
                        onChange={(value) => setAttributes({list_title: value})}
                    />
                    <SelectControl
                        label={__('Show Headers?', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.show_headers}
                        options={[
                            {label: 'Yes', value: 'yes'},
                            {label: 'No', value: 'no'}
                        ]}
                        onChange={(value) => setAttributes({show_headers: value})}
                    />
                    <SelectControl
                        label={__('Show Time?', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.show_time}
                        options={[
                            {label: 'Yes', value: 'yes'},
                            {label: 'No', value: 'no'}
                        ]}
                        onChange={(value) => setAttributes({show_time: value})}
                    />
                    <SelectControl
                        label={__('Show Phone?', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.show_phone}
                        options={[
                            {label: 'Yes', value: 'yes'},
                            {label: 'No', value: 'no'}
                        ]}
                        onChange={(value) => setAttributes({show_phone: value})}
                    />
                    <SelectControl
                        label={__('Show Email?', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.show_email}
                        options={[
                            {label: 'Yes', value: 'yes'},
                            {label: 'No', value: 'no'}
                        ]}
                        onChange={(value) => setAttributes({show_email: value})}
                    />
                    <SelectControl
                        label={__('Order By', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.order_by}
                        options={[
                            {label: 'First Date', value: 'first_date'},
                            {label: 'Last Date', value: 'last_date'},
                            {label: 'Title', value: 'title'},
                            {label: 'ID', value: 'id'}
                        ]}
                        onChange={(value) => setAttributes({order_by: value})}
                    />
                    <SelectControl
                        label={__('Order', 'pta-volunteer-sign-up-sheets')}
                        value={attributes.order}
                        options={[
                            {label: 'Ascending', value: 'ASC'},
                            {label: 'Descending', value: 'DESC'}
                        ]}
                        onChange={(value) => setAttributes({order: value})}
                    />
                </PanelBody>
            </InspectorControls>
        </div>
    );
};
registerBlockType('pta-volunteer-sus/signup-sheet', {
    edit: Edit,
    save: () => null
});

registerBlockType('pta-volunteer-sus/user-signups', {
    attributes: {
        show_time: {
            type: 'string',
            default: 'yes'
        }
    },
    edit: ({ attributes, setAttributes }) => {
        const blockProps = useBlockProps();

        return (
            <div { ...blockProps }>
                <ServerSideRender
                    block="pta-volunteer-sus/user-signups"
                    attributes={ attributes }
                />
                <InspectorControls>
                    <PanelBody title={__('Display Settings', 'pta-volunteer-sign-up-sheets')}>
                        <p>{__('Configure which columns to display in the user signups list.', 'pta-volunteer-sign-up-sheets')}</p>
                        <SelectControl
                            label={__('Show Time Columns?', 'pta-volunteer-sign-up-sheets')}
                            value={attributes.show_time}
                            options={[
                                { label: 'Yes', value: 'yes' },
                                { label: 'No', value: 'no' }
                            ]}
                            onChange={(value) => setAttributes({ show_time: value })}
                        />
                    </PanelBody>
                </InspectorControls>
            </div>
        );
    },
    save: () => null
});
registerBlockType('pta-volunteer-sus/upcoming-events', {
    attributes: {
        title: {
            type: 'string',
            default: __('Current Volunteer Opportunities', 'pta-volunteer-sign-up-sheets')
        },
        num_items: {
            type: 'number',
            default: 10
        },
        show_what: {
            type: 'string',
            default: 'both'
        },
        sort_by: {
            type: 'string',
            default: 'first_date'
        },
        order: {
            type: 'string',
            default: 'ASC'
        },
        list_class: {
            type: 'string',
            default: ''
        }
    },
    edit: ({ attributes, setAttributes }) => {
        const blockProps = useBlockProps();

        return (
            <div { ...blockProps }>
                <ServerSideRender
                    block="pta-volunteer-sus/upcoming-events"
                    attributes={ attributes }
                />
                <InspectorControls>
                    <PanelBody title={__('Widget Settings', 'pta-volunteer-sign-up-sheets')}>
                        <TextControl
                            label={__('Title', 'pta-volunteer-sign-up-sheets')}
                            value={attributes.title}
                            onChange={(value) => setAttributes({ title: value })}
                        />
                        <TextControl
                            label={__('# of items to show (-1 for all)', 'pta-volunteer-sign-up-sheets')}
                            type="number"
                            value={attributes.num_items}
                            onChange={(value) => setAttributes({ num_items: parseInt(value) })}
                        />
                        <SelectControl
                            label={__('What to show?', 'pta-volunteer-sign-up-sheets')}
                            value={attributes.show_what}
                            options={[
                                { label: __('Both', 'pta-volunteer-sign-up-sheets'), value: 'both' },
                                { label: __('Volunteer Events (with sign-ups)', 'pta-volunteer-sign-up-sheets'), value: 'signups' },
                                { label: __('No Sign-Up Events (display events only)', 'pta-volunteer-sign-up-sheets'), value: 'events' }
                            ]}
                            onChange={(value) => setAttributes({ show_what: value })}
                        />
                        <SelectControl
                            label={__('Sort By', 'pta-volunteer-sign-up-sheets')}
                            value={attributes.sort_by}
                            options={[
                                { label: __('First Date', 'pta-volunteer-sign-up-sheets'), value: 'first_date' },
                                { label: __('Last Date', 'pta-volunteer-sign-up-sheets'), value: 'last_date' },
                                { label: __('Title', 'pta-volunteer-sign-up-sheets'), value: 'title' },
                                { label: __('Sheet ID', 'pta-volunteer-sign-up-sheets'), value: 'id' }
                            ]}
                            onChange={(value) => setAttributes({ sort_by: value })}
                        />
                        <SelectControl
                            label={__('Sort Order', 'pta-volunteer-sign-up-sheets')}
                            value={attributes.order}
                            options={[
                                { label: __('Ascending', 'pta-volunteer-sign-up-sheets'), value: 'ASC' },
                                { label: __('Descending', 'pta-volunteer-sign-up-sheets'), value: 'DESC' }
                            ]}
                            onChange={(value) => setAttributes({ order: value })}
                        />
                        <TextControl
                            label={__('CSS Class for ul list of signups', 'pta-volunteer-sign-up-sheets')}
                            value={attributes.list_class}
                            onChange={(value) => setAttributes({ list_class: value })}
                        />
                    </PanelBody>
                </InspectorControls>
            </div>
        );
    },
    save: () => null
});
registerBlockType('pta-volunteer-sus/validation-form', {
    edit: ({ attributes, setAttributes }) => {
        const blockProps = useBlockProps();

        return (
            <div { ...blockProps }>
                <ServerSideRender
                    block="pta-volunteer-sus/validation-form"
                    attributes={ attributes }
                />
                <InspectorControls>
                    <PanelBody title={__('Display Settings', 'pta-volunteer-sign-up-sheets')}>
                        <p>{__('If you use this on a page with the main sign up sheets block, you may want to hide the output of this block when validated so there a no messages saying you are already validated on every page.', 'pta-volunteer-sign-up-sheets')}</p>
                        <SelectControl
                            label={__('Hide When Validated?', 'pta-volunteer-sign-up-sheets')}
                            value={attributes.hide_when_validated}
                            options={[
                                { label: 'Yes', value: 'yes' },
                                { label: 'No', value: 'no' }
                            ]}
                            onChange={(value) => setAttributes({ hide_when_validated: value })}
                        />
                    </PanelBody>
                </InspectorControls>
            </div>
        );
    },
    save: () => null
});
// original block - deprecated
registerBlockType('pta-volunteer-sus-block/block-pta-volunteer-sus-block', {
    title: __('Sign Up Sheets (Legacy)', 'pta-volunteer-sign-up-sheets'),
    icon: 'welcome-write-blog',
    category: 'widgets',
    attributes: {
        codeType: { default: 'signups' },
        id: { default: '' },
        date: { default: '' },
        group: { default: '' },
        list_title: { default: '' },
        show_headers: { default: 'yes' },
        show_time: { default: 'yes' },
        show_phone: { default: 'no' },
        show_email: { default: 'no' },
        show_date_start: { default: 'yes' },
        show_date_end: { default: 'yes' },
        order_by: { default: 'first_date' },
        order: { default: 'ASC' }
    },
    deprecated: [{
        attributes: {
            codeType: { default: 'signups' },
            id: { default: '' },
            date: { default: '' },
            group: { default: '' },
            list_title: { default: '' },
            show_headers: { default: 'yes' },
            show_time: { default: 'yes' },
            show_phone: { default: 'no' },
            show_email: { default: 'no' },
            show_date_start: { default: 'yes' },
            show_date_end: { default: 'yes' },
            order_by: { default: 'first_date' },
            order: { default: 'ASC' }
        },
        save: ({ attributes }) => {
            return createElement('div', {
                className: 'wp-block-pta-volunteer-sus-block-block-pta-volunteer-sus-block'
            }, `[pta_sign_up_sheet id="${attributes.id}" date="${attributes.date}" group="${attributes.group}" list_title="${attributes.list_title}" show_headers="${attributes.show_headers}" show_time="${attributes.show_time}" show_phone="${attributes.show_phone}" show_email="${attributes.show_email}" show_date_start="${attributes.show_date_start}" show_date_end="${attributes.show_date_end}" order_by="${attributes.order_by}" order="${attributes.order}" ]`);
        }
    }],
    edit: ({ attributes, clientId }) => {
        const blockProps = useBlockProps();

        const convertToNewBlock = () => {
            console.log('Current attributes:', attributes);
            const newAttributes = {
                id: attributes.id || '',
                date: attributes.date || '',
                group: attributes.group || '',
                list_title: attributes.list_title || '',
                show_headers: attributes.show_headers || 'yes',
                show_time: attributes.show_time || 'yes',
                show_phone: attributes.show_phone || 'no',
                show_email: attributes.show_email || 'no',
                order_by: attributes.order_by || 'first_date',
                order: attributes.order || 'ASC'
            };
            console.log('New attributes:', newAttributes);

            try {
                const newBlock = createBlock('pta-volunteer-sus/signup-sheet', newAttributes);
                wp.data.dispatch('core/block-editor').replaceBlock(clientId, newBlock);
            } catch (error) {
                console.error('Block conversion error:', error);
            }
        };

        return (
            <div {...blockProps}>
                <div className="components-notice is-warning">
                    <p>{__('This block has been deprecated. Please convert it to the new Sign Up Sheets block to maintain functionality.', 'pta-volunteer-sign-up-sheets')}</p>
                    <pre>Current block attributes: {JSON.stringify(attributes, null, 2)}</pre>
                    <Button
                        isPrimary
                        onClick={convertToNewBlock}
                    >
                        {__('Convert to New Block', 'pta-volunteer-sign-up-sheets')}
                    </Button>
                </div>
            </div>
        );
    },
    save: () => null
});