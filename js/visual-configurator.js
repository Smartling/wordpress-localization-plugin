/* global wp, jQuery, smartlingVisualConfigurator */
(function () {
    const { render, createElement: el, useState, useEffect, useCallback, useRef, Fragment } = wp.element;
    const {
        Button,
        Card,
        CardBody,
        CheckboxControl,
        CardHeader,
        Modal,
        Notice,
        SelectControl,
        Spinner,
        TextControl,
        __experimentalVStack: VStack,
    } = wp.components;

    const settings = window.smartlingVisualConfigurator || {};
    const REPLACER_OPTIONS = settings.replacerOptions || {};
    const REFERENCED_CONTENT_TYPES = [
        { label: 'Post-based (attachment)', value: 'attachment' },
        { label: 'Post-based (post)', value: 'postbased' },
    ];

    function buildReplacerSelect(value, onChange) {
        const options = Object.keys(REPLACER_OPTIONS).map((id) => ({
            label: REPLACER_OPTIONS[id],
            value: id,
        }));
        return el(SelectControl, {
            label: 'Rule',
            value,
            options: [{ label: '(none)', value: '' }, ...options],
            onChange,
        });
    }

    function tryParseJson(value) {
        if (typeof value !== 'string') return null;
        const trimmed = value.trim();
        if (trimmed === '' || (trimmed[0] !== '{' && trimmed[0] !== '[')) {
            return null;
        }
        try {
            const parsed = JSON.parse(trimmed);
            if (parsed && typeof parsed === 'object') return parsed;
        } catch (e) {
            // not JSON
        }
        return null;
    }

    function joinPath(prefix, segment) {
        if (typeof segment === 'number' || /^\d+$/.test(segment)) {
            // Emit a wildcard for array indices: clicking one element should produce
            // a rule that applies to every sibling at the same position, which is what
            // dynamic JSON blobs (Elementor widgets, etc.) need.
            return `${prefix}[*]`;
        }
        if (/^[A-Za-z_][\w]*$/.test(segment)) {
            return prefix === '$' ? `$.${segment}` : `${prefix}.${segment}`;
        }
        return `${prefix}['${segment.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}']`;
    }

    function valueIsLeaf(value) {
        return value === null || ['string', 'number', 'boolean'].includes(typeof value);
    }

    function formatLeaf(value) {
        if (typeof value === 'string') {
            const trimmed = value.length > 80 ? `${value.slice(0, 80)}…` : value;
            return `"${trimmed}"`;
        }
        return String(value);
    }

    const KEY_PATTERN = /^[A-Za-z_][\w-]*$/;
    const PREVIEW_DEBOUNCE_MS = 400;

    // String properties of an object that can be used to tell otherwise identical structures apart
    function scalarProps(object) {
        const props = {};
        Object.entries(object).forEach(([k, v]) => {
            if (typeof v === 'string' && v !== '' && v.length <= 64 && k !== 'id' && k !== '_id') {
                props[k] = v;
            }
        });
        return props;
    }

    function findWidgetType(ancestors) {
        for (let i = ancestors.length - 1; i >= 0; i--) {
            if (ancestors[i].elType === 'widget' && ancestors[i].widgetType) {
                return ancestors[i].widgetType;
            }
        }
        return '';
    }

    function describeCondition(c) {
        return `${c.key} = "${c.value}" (${c.ancestor === 0 ? 'same object' : `${c.ancestor} levels up`})`;
    }

    function post(action, data) {
        return jQuery.post(settings.ajaxUrl, { action, _wpnonce: settings.nonce, ...data });
    }

    function JsonNode({ value, path, metaKey, onAddRule, rulesByPath, depth = 0, ancestors = [], keys = [] }) {
        const [expanded, setExpanded] = useState(true);
        if (valueIsLeaf(value)) {
            const existing = rulesByPath[`${metaKey}|${path}`];
            const isString = typeof value === 'string';
            const isNumeric = typeof value === 'number' || (isString && /^\d+$/.test(value));
            return el(
                'div',
                { className: 'svc-leaf', style: { padding: '4px 0 4px 16px', borderLeft: '1px solid #ddd' } },
                el('code', { style: { color: '#0073aa' } }, path),
                ' = ',
                el('span', { style: { color: '#444' } }, formatLeaf(value)),
                ' ',
                existing
                    ? el(
                          'span',
                          { style: { marginLeft: 8, padding: '0 6px', background: '#eef', borderRadius: 4 } },
                          'rule: ',
                          existing.replacerId,
                      )
                    : el(
                          Button,
                          {
                              variant: 'link',
                              onClick: () => onAddRule({ path, metaKey, value, isString, isNumeric, keys, ancestors }),
                          },
                          'Add rule',
                      ),
            );
        }
        const entries = Array.isArray(value)
            ? value.map((v, i) => [i, v])
            : Object.entries(value);
        const childAncestors = Array.isArray(value) ? ancestors : [...ancestors, { ...scalarProps(value), elType: value.elType, widgetType: value.widgetType }];
        return el(
            'div',
            { style: { paddingLeft: depth === 0 ? 0 : 16 } },
            el(
                Button,
                {
                    variant: 'tertiary',
                    onClick: () => setExpanded(!expanded),
                    style: { padding: '0 4px' },
                },
                expanded ? '▾' : '▸',
                ' ',
                Array.isArray(value) ? `array(${entries.length})` : `object(${entries.length})`,
            ),
            expanded &&
                el(
                    'div',
                    { style: { borderLeft: '1px solid #ddd', marginLeft: 4 } },
                    entries.map(([k, v]) =>
                        el(
                            'div',
                            { key: String(k) },
                            valueIsLeaf(v)
                                ? null
                                : el('div', { style: { paddingLeft: 16, color: '#666' } }, String(k), ':'),
                            el(JsonNode, {
                                key: String(k),
                                value: v,
                                path: joinPath(path, k),
                                metaKey,
                                onAddRule,
                                rulesByPath,
                                depth: depth + 1,
                                ancestors: childAncestors,
                                keys: Array.isArray(value) ? keys : [...keys, String(k)],
                            }),
                        ),
                    ),
                ),
        );
    }

    function MetaField({ name, value, onAddRule, rulesByPath }) {
        const parsed = tryParseJson(value);
        return el(
            Card,
            { style: { marginBottom: 12 } },
            el(CardHeader, null, el('strong', null, name), parsed ? ' (JSON)' : ' (plain)'),
            el(
                CardBody,
                null,
                parsed
                    ? el(JsonNode, {
                          value: parsed,
                          path: '$',
                          metaKey: name,
                          onAddRule,
                          rulesByPath,
                      })
                    : el(
                          'div',
                          null,
                          el('code', { style: { color: '#0073aa' } }, name),
                          ' = ',
                          el('span', null, formatLeaf(value)),
                          ' ',
                          rulesByPath[name]
                              ? el(
                                    'span',
                                    { style: { marginLeft: 8, padding: '0 6px', background: '#eef', borderRadius: 4 } },
                                    'rule: ',
                                    rulesByPath[name].replacerId,
                                )
                              : el(
                                    Button,
                                    {
                                        variant: 'link',
                                        onClick: () =>
                                            onAddRule({
                                                path: '',
                                                metaKey: name,
                                                value,
                                                isString: typeof value === 'string',
                                                isNumeric: typeof value === 'number' || /^\d+$/.test(String(value)),
                                            }),
                                    },
                                    'Add rule',
                                ),
                      ),
            ),
        );
    }

    function RuleEditor({ draft, contentId, onCancel, onSave }) {
        const [replacerId, setReplacerId] = useState('copy');
        const [refType, setRefType] = useState('attachment');
        const [mode, setMode] = useState('position');
        const [keyCount, setKeyCount] = useState(1);
        const [limitToWidget, setLimitToWidget] = useState(false);
        const [selected, setSelected] = useState({});
        const [preview, setPreview] = useState(null);

        const keys = draft ? draft.keys || [] : [];
        const ancestors = draft ? draft.ancestors || [] : [];
        const widgetType = findWidgetType(ancestors);
        const canGeneralize = draft && draft.path !== '' && keys.length > 0 && keys.every((k) => KEY_PATTERN.test(k));

        // One option per string property of every enclosing object, nearest object first
        const conditionOptions = [];
        for (let distance = 0; distance < ancestors.length; distance++) {
            const object = ancestors[ancestors.length - 1 - distance];
            Object.entries(object).forEach(([key, val]) => {
                // The leaf's own value would limit the rule to that single source string
                const isLeafItself = distance === 0 && key === keys[keys.length - 1];
                if (typeof val === 'string' && key !== 'elType' && key !== 'widgetType' && !isLeafItself) {
                    conditionOptions.push({ id: `${distance}|${key}`, ancestor: distance, key, value: val });
                }
            });
        }
        const conditions = conditionOptions.filter((o) => selected[o.id]).map(({ ancestor, key, value }) => ({ ancestor, key, value }));
        const extended = mode === 'anywhere';
        const propertyPath = !draft
            ? ''
            : extended
                ? `$..${keys.slice(-keyCount).join('.')}`
                : draft.path;
        const activeWidget = extended && limitToWidget ? widgetType : '';
        const activeConditions = extended ? conditions : [];
        const composedReplacerId = replacerId === 'related' ? `related|${refType}` : replacerId;
        const previewKey = JSON.stringify([propertyPath, activeWidget, activeConditions, composedReplacerId, mode]);

        useEffect(() => {
            if (!draft || draft.path === '' || !replacerId) {
                setPreview(null);
                return undefined;
            }
            let cancelled = false;
            const timer = setTimeout(async () => {
                try {
                    const response = await post(settings.actions.preview, {
                        id: contentId,
                        metaKey: draft.metaKey,
                        propertyPath,
                        replacerId: composedReplacerId,
                        widgetType: activeWidget,
                        conditions: JSON.stringify(activeConditions),
                        matchMode: mode,
                    });
                    if (!cancelled) {
                        setPreview(response && response.success
                            ? response.data
                            : { error: (response && response.data && response.data.message) || 'Preview failed' });
                    }
                } catch (e) {
                    if (!cancelled) setPreview({ error: 'Preview failed' });
                }
            }, PREVIEW_DEBOUNCE_MS);
            return () => {
                cancelled = true;
                clearTimeout(timer);
            };
        }, [previewKey, draft && draft.metaKey, contentId]);

        if (!draft) return null;
        return el(
            Modal,
            {
                title: 'Add rule',
                onRequestClose: onCancel,
                shouldCloseOnClickOutside: false,
                style: { maxWidth: 620 },
            },
            el('p', null,
                'Target: ',
                el('code', null, draft.metaKey + (draft.path ? ' ' + draft.path : '')),
            ),
            el(VStack, { spacing: 3 },
                buildReplacerSelect(replacerId, setReplacerId),
                replacerId === 'related'
                    ? el(SelectControl, {
                          label: 'Referenced content type',
                          value: refType,
                          options: REFERENCED_CONTENT_TYPES,
                          onChange: setRefType,
                      })
                    : null,
                canGeneralize
                    ? el(SelectControl, {
                          label: 'Match',
                          value: mode,
                          options: [
                              { label: 'This position only (array indices become wildcards)', value: 'position' },
                              { label: 'Anywhere in the content with this key path', value: 'anywhere' },
                          ],
                          onChange: setMode,
                          help: extended ? 'Also matches widgets nested at a different depth.' : undefined,
                      })
                    : null,
                extended && keys.length > 1
                    ? el(SelectControl, {
                          label: 'Keys in path',
                          value: String(keyCount),
                          options: keys.map((k, idx) => ({
                              label: keys.slice(-(idx + 1)).join('.'),
                              value: String(idx + 1),
                          })),
                          onChange: (v) => setKeyCount(parseInt(v, 10)),
                          help: 'Use more keys when the last key alone is too generic.',
                      })
                    : null,
                extended && widgetType
                    ? el(CheckboxControl, {
                          label: `Only inside "${widgetType}" widgets`,
                          checked: limitToWidget,
                          onChange: setLimitToWidget,
                      })
                    : null,
                extended && conditionOptions.length > 0
                    ? el('div', null,
                          el('strong', null, 'Only when'),
                          conditionOptions.map((o) =>
                              el(CheckboxControl, {
                                  key: o.id,
                                  label: describeCondition(o),
                                  checked: !!selected[o.id],
                                  onChange: (checked) => setSelected({ ...selected, [o.id]: checked }),
                              }),
                          ),
                      )
                    : null,
                preview && preview.error ? el(Notice, { status: 'warning', isDismissible: false }, preview.error) : null,
                preview && !preview.error
                    ? el('div', { style: { background: '#f6f7f7', padding: 8, maxHeight: 180, overflow: 'auto' } },
                          el('strong', null, `${preview.count} match${preview.count === 1 ? '' : 'es'} in this content`),
                          el('ul', { style: { margin: '4px 0 0 16px', listStyle: 'disc' } },
                              preview.values.map((v, idx) => el('li', { key: idx }, el('code', null, v))),
                          ),
                          preview.count > preview.values.length ? el('em', null, `…and ${preview.count - preview.values.length} more`) : null,
                      )
                    : null,
                el('div', null,
                    el(Button, {
                        variant: 'primary',
                        disabled: !replacerId,
                        onClick: () => onSave({
                            metaKey: draft.metaKey,
                            propertyPath,
                            replacerId: composedReplacerId,
                            widgetType: activeWidget,
                            conditions: JSON.stringify(activeConditions),
                            matchMode: mode,
                        }),
                    }, 'Save rule'),
                    ' ',
                    el(Button, { variant: 'secondary', onClick: onCancel }, 'Cancel'),
                ),
            ),
        );
    }

    function VisualConfigurator() {
        const [contentId, setContentId] = useState('');
        const [content, setContent] = useState(null);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');
        const [rules, setRules] = useState([]);
        const [draft, setDraft] = useState(null);
        const [draftSeq, setDraftSeq] = useState(0);
        const [deleteConfirmId, setDeleteConfirmId] = useState(null);
        const [importResult, setImportResult] = useState(null);
        // Every opened draft gets a fresh editor, otherwise match mode, keys and ticked conditions leak into the next rule
        const openDraft = useCallback((next) => {
            setDraftSeq((n) => n + 1);
            setDraft(next);
        }, []);
        const fileInput = useRef(null);

        const refreshRules = useCallback(async () => {
            try {
                const response = await jQuery.post(settings.ajaxUrl, {
                    action: settings.actions.list,
                    _wpnonce: settings.nonce,
                });
                if (response && response.success) {
                    setRules(response.data.rules || []);
                }
            } catch (e) {
                setError('Failed to load rules: ' + (e.message || 'unknown'));
            }
        }, []);

        useEffect(() => {
            refreshRules();
        }, [refreshRules]);

        const loadContent = useCallback(async () => {
            if (!contentId) return;
            setLoading(true);
            setError('');
            setContent(null);
            try {
                // Resolve the post type from wp_posts so the user doesn't have to pick it.
                const typeResp = await jQuery.post(settings.ajaxUrl, {
                    action: settings.actions.resolveType,
                    _wpnonce: settings.nonce,
                    id: contentId,
                });
                if (!typeResp || !typeResp.success) {
                    throw new Error(typeResp?.data?.message || 'Could not resolve post type');
                }
                const type = typeResp.data.type;
                const url = `${settings.restRoot}/assets/${type}-${contentId}/raw`;
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': settings.restNonce },
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                setContent(data);
            } catch (e) {
                setError('Failed to load content: ' + (e.message || 'unknown'));
            } finally {
                setLoading(false);
            }
        }, [contentId]);

        const handleSaveRule = useCallback(async (payload) => {
            try {
                const response = await jQuery.post(settings.ajaxUrl, {
                    action: settings.actions.save,
                    _wpnonce: settings.nonce,
                    ...payload,
                });
                if (!response || !response.success) {
                    setError(response?.data?.message || 'Save failed');
                    return;
                }
                setDraft(null);
                await refreshRules();
            } catch (e) {
                setError('Save failed: ' + (e.message || 'unknown'));
            }
        }, [refreshRules]);

        const handleDeleteRule = useCallback((id) => {
            setDeleteConfirmId(id);
        }, []);

        const handleConfirmDelete = useCallback(async () => {
            const id = deleteConfirmId;
            setDeleteConfirmId(null);
            try {
                await jQuery.post(settings.ajaxUrl, {
                    action: settings.actions.delete,
                    _wpnonce: settings.nonce,
                    id,
                });
                await refreshRules();
            } catch (e) {
                setError('Delete failed: ' + (e.message || 'unknown'));
            }
        }, [deleteConfirmId, refreshRules]);

        const handleExport = useCallback(async () => {
            try {
                const response = await post(settings.actions.export, {});
                if (!response || !response.success) {
                    setError((response && response.data && response.data.message) || 'Export failed');
                    return;
                }
                const blob = new Blob([JSON.stringify(response.data.export, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'smartling-visual-configurator-rules.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            } catch (e) {
                setError('Export failed: ' + (e.message || 'unknown'));
            }
        }, []);

        const handleImportFile = useCallback(async (event) => {
            const file = event.target.files && event.target.files[0];
            event.target.value = '';
            if (!file) return;
            setError('');
            setImportResult(null);
            try {
                const payload = await file.text();
                const response = await post(settings.actions.import, { payload });
                if (!response || !response.success) {
                    setError((response && response.data && response.data.message) || 'Import failed');
                    return;
                }
                setImportResult(response.data);
                await refreshRules();
            } catch (e) {
                setError('Import failed: ' + (e.message || 'unknown'));
            }
        }, [refreshRules]);

        const rulesByPath = {};
        rules.forEach((r) => {
            const key = r.propertyPath ? `${r.metaKey}|${r.propertyPath}` : r.metaKey;
            rulesByPath[key] = r;
        });

        return el(Fragment, null,
            el(Card, { style: { marginBottom: 16 } },
                el(CardHeader, null, 'Load content sample'),
                el(CardBody, null,
                    el(VStack, { spacing: 3 },
                        el(TextControl, {
                            label: 'Post ID',
                            help: 'Any post-based content (page, post, attachment, custom post type) — the post type is resolved automatically from wp_posts.',
                            value: contentId,
                            onChange: setContentId,
                        }),
                        el(Button, { variant: 'primary', onClick: loadContent, disabled: !contentId }, 'Load'),
                    ),
                ),
            ),
            error ? el(Notice, { status: 'error', isDismissible: false }, error) : null,
            loading ? el(Spinner) : null,
            content && el(Card, { style: { marginBottom: 16 } },
                el(CardHeader, null, 'Detected meta fields'),
                el(CardBody, null,
                    Object.entries(content.meta || {}).map(([name, value]) =>
                        el(MetaField, {
                            key: name,
                            name,
                            value,
                            onAddRule: openDraft,
                            rulesByPath,
                        }),
                    ),
                ),
            ),
            el(RuleEditor, {
                key: draftSeq,
                draft,
                contentId,
                onCancel: () => setDraft(null),
                onSave: handleSaveRule,
            }),
            deleteConfirmId !== null && el(Modal, {
                title: 'Delete rule',
                onRequestClose: () => setDeleteConfirmId(null),
                shouldCloseOnClickOutside: true,
            },
                el('p', null, 'Are you sure you want to delete this rule?'),
                el('div', null,
                    el(Button, { variant: 'primary', isDestructive: true, onClick: handleConfirmDelete }, 'Delete'),
                    ' ',
                    el(Button, { variant: 'secondary', onClick: () => setDeleteConfirmId(null) }, 'Cancel'),
                ),
            ),
            importResult && el(Notice, { status: importResult.invalid.length > 0 ? 'warning' : 'success', onRemove: () => setImportResult(null) },
                `Import finished: ${importResult.added} added, ${importResult.skipped} skipped (already exist), ${importResult.invalid.length} invalid. Existing rules were not changed.`,
                importResult.invalid.length > 0 && el('ul', { style: { margin: '4px 0 0 16px', listStyle: 'disc' } },
                    importResult.invalid.map((i) => el('li', { key: i.index }, `Rule #${i.index}: ${i.message}`)),
                ),
            ),
            el(Card, null,
                el(CardHeader, null,
                    el('span', null, `Saved rules (${rules.length})`),
                    el('span', null,
                        el(Button, { variant: 'secondary', onClick: handleExport, disabled: rules.length === 0 }, 'Export'),
                        ' ',
                        el(Button, { variant: 'secondary', onClick: () => fileInput.current && fileInput.current.click() }, 'Import'),
                        el('input', {
                            ref: fileInput,
                            type: 'file',
                            accept: '.json,application/json',
                            style: { display: 'none' },
                            onChange: handleImportFile,
                        }),
                    ),
                ),
                el(CardBody, null,
                    rules.length === 0
                        ? el('em', null, 'No rules yet.')
                        : el('table', { className: 'widefat' },
                            el('thead', null,
                                el('tr', null,
                                    el('th', null, 'Meta key'),
                                    el('th', null, 'Path'),
                                    el('th', null, 'Widget'),
                                    el('th', null, 'Only when'),
                                    el('th', null, 'Rule'),
                                    el('th', null, ''),
                                ),
                            ),
                            el('tbody', null,
                                rules.map((r) =>
                                    el('tr', { key: r.id },
                                        el('td', null, el('code', null, r.metaKey)),
                                        el('td', null, el('code', null, r.propertyPath || '(whole field)')),
                                        el('td', null, r.widgetType ? el('code', null, r.widgetType) : '—'),
                                        el('td', null, (r.conditions || []).length > 0
                                            ? (r.conditions || []).map((c, idx) => el('div', { key: idx }, describeCondition(c)))
                                            : '—'),
                                        el('td', null, r.replacerId),
                                        el('td', null,
                                            el(Button, {
                                                variant: 'link',
                                                isDestructive: true,
                                                onClick: () => handleDeleteRule(r.id),
                                            }, 'Delete'),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                ),
            ),
        );
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('smartling-visual-configurator-root');
        if (!root) return;
        if (typeof settings.ajaxUrl === 'undefined') {
            root.innerHTML = '<p>Configurator not configured.</p>';
            return;
        }
        render(el(VisualConfigurator), root);
    });
})();
