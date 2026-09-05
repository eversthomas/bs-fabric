import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    CheckboxControl,
    Notice,
    Panel,
    PanelBody,
    Placeholder,
    SelectControl,
    Spinner,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

function getConfig() {
    return (
        window.bsFabricEntryEmbed || {
            ajaxUrl: '',
            nonce: '',
            strings: {},
        }
    );
}

function postAjax(action, params) {
    const config = getConfig();
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce);

    Object.keys(params).forEach((key) => {
        body.set(key, params[key]);
    });

    return fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
    }).then((response) => response.json());
}

export default function Edit({ attributes, setAttributes }) {
    const { template, entryIds, view } = attributes;
    const blockProps = useBlockProps();
    const strings = getConfig().strings || {};

    const [templates, setTemplates] = useState([]);
    const [entries, setEntries] = useState([]);
    const [loadingEntries, setLoadingEntries] = useState(false);

    useEffect(() => {
        postAjax('bs_fabric_embed_templates', {}).then((payload) => {
            if (payload && payload.success) {
                setTemplates(payload.data.templates || []);
            }
        });
    }, []);

    useEffect(() => {
        if (!template) {
            setEntries([]);
            return;
        }

        setLoadingEntries(true);

        postAjax('bs_fabric_embed_entries', { template }).then((payload) => {
            setLoadingEntries(false);

            if (payload && payload.success) {
                setEntries(payload.data.entries || []);
            }
        });
    }, [template]);

    function toggleEntry(id, checked) {
        const next = checked
            ? [...entryIds, id]
            : entryIds.filter((existingId) => existingId !== id);

        setAttributes({ entryIds: next });
    }

    const hasSelection = Boolean(template) && entryIds.length > 0;

    return (
        <>
            <InspectorControls>
                <Panel>
                    <PanelBody title={strings.title || __('Fabric-Inhalt', 'bs-fabric')}>
                        <SelectControl
                            label={strings.templateLabel || __('Template', 'bs-fabric')}
                            value={template}
                            options={[
                                {
                                    label: strings.templatePlaceholder || __('— auswählen —', 'bs-fabric'),
                                    value: '',
                                },
                                ...templates.map((item) => ({
                                    label: item.label,
                                    value: item.name,
                                })),
                            ]}
                            onChange={(value) => setAttributes({ template: value, entryIds: [] })}
                        />

                        {template && (
                            <SelectControl
                                label={strings.viewLabel || __('Ansicht', 'bs-fabric')}
                                value={view}
                                options={[
                                    { label: 'Card', value: 'card' },
                                    { label: 'Detail', value: 'detail' },
                                    { label: 'List', value: 'list' },
                                ]}
                                onChange={(value) => setAttributes({ view: value })}
                            />
                        )}

                        {template && loadingEntries && <Spinner />}

                        {template && !loadingEntries && entries.length === 0 && (
                            <Notice status="info" isDismissible={false}>
                                {strings.noEntries || __('Keine Einträge in diesem Template.', 'bs-fabric')}
                            </Notice>
                        )}

                        {template &&
                            !loadingEntries &&
                            entries.map((entry) => (
                                <CheckboxControl
                                    key={entry.id}
                                    label={
                                        entry.status !== 'published'
                                            ? entry.title + ' (' + entry.status + ')'
                                            : entry.title
                                    }
                                    checked={entryIds.includes(entry.id)}
                                    onChange={(checked) => toggleEntry(entry.id, checked)}
                                />
                            ))}
                    </PanelBody>
                </Panel>
            </InspectorControls>
            <div {...blockProps}>
                {hasSelection ? (
                    <ServerSideRender block="bs-fabric/entry-embed" attributes={attributes} />
                ) : (
                    <Placeholder
                        icon="layout"
                        label={strings.title || __('Fabric-Inhalt', 'bs-fabric')}
                        instructions={
                            strings.placeholderInstructions
                            || __('Wähle rechts ein Template und mindestens einen Eintrag aus.', 'bs-fabric')
                        }
                    />
                )}
            </div>
        </>
    );
}
