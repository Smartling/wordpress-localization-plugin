/* global wp, jQuery, smartlingVisualConfigurator */
(function () {
    const { render, createElement: el, useState, useEffect, useCallback, Fragment } = wp.element;
    const {
        Button,
        Card,
        CardBody,
        CardHeader,
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
            return `${prefix}[${segment}]`;
        }
        if (/^[A-Za-z_][\w]*$/.test(segment)) {
            return prefix === '$' ? `$.${segment}` : `${prefix}.${segment}`;
        }
        return `${prefix}['${segment.replace(/'/g, "\\'")}']`;
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

    function JsonNode({ value, path, metaKey, contentType, onAddRule, rulesByPath, depth = 0 }) {
        const [expanded, setExpanded] = useState(depth < 2);
        if (valueIsLeaf(value)) {
            const existing = rulesByPath[path];
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
                              onClick: () => onAddRule({ path, metaKey, contentType, value, isString, isNumeric }),
                          },
                          'Add rule',
                      ),
            );
        }
        const entries = Array.isArray(value)
            ? value.map((v, i) => [i, v])
            : Object.entries(value);
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
                                contentType,
                                onAddRule,
                                rulesByPath,
                                depth: depth + 1,
                            }),
                        ),
                    ),
                ),
        );
    }

    function MetaField({ name, value, contentType, onAddRule, rulesByPath }) {
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
                          contentType,
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
                                                contentType,
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

    function RuleEditor({ draft, onCancel, onSave }) {
        const [replacerId, setReplacerId] = useState('copy');
        const [refType, setRefType] = useState('attachment');
        if (!draft) return null;
        const composedReplacerId = replacerId === 'related' ? `related|${refType}` : replacerId;
        return el(
            Card,
            { style: { marginTop: 12, border: '2px solid #0073aa' } },
            el(CardHeader, null, 'Add rule for ', el('code', null, draft.metaKey + (draft.path ? ' ' + draft.path : ''))),
            el(
                CardBody,
                null,
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
                    el('div', null,
                        el(Button, {
                            variant: 'primary',
                            disabled: !replacerId,
                            onClick: () => onSave({
                                contentType: draft.contentType,
                                metaKey: draft.metaKey,
                                propertyPath: draft.path,
                                replacerId: composedReplacerId,
                            }),
                        }, 'Save rule'),
                        ' ',
                        el(Button, { variant: 'secondary', onClick: onCancel }, 'Cancel'),
                    ),
                ),
            ),
        );
    }

    function VisualConfigurator() {
        const [contentType, setContentType] = useState('page');
        const [contentId, setContentId] = useState('');
        const [content, setContent] = useState(null);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');
        const [rules, setRules] = useState([]);
        const [draft, setDraft] = useState(null);

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
                const url = `${settings.restRoot}/assets/${contentType}-${contentId}/raw`;
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
        }, [contentType, contentId]);

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

        const handleDeleteRule = useCallback(async (id) => {
            if (!confirm('Delete this rule?')) return;
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
        }, [refreshRules]);

        const rulesByPath = {};
        rules
            .filter((r) => r.contentType === contentType || r.contentType === '*')
            .forEach((r) => {
                const key = r.propertyPath ? `${r.metaKey}|${r.propertyPath}` : r.metaKey;
                rulesByPath[r.propertyPath || r.metaKey] = r;
                rulesByPath[key] = r;
            });

        return el(Fragment, null,
            el(Card, { style: { marginBottom: 16 } },
                el(CardHeader, null, 'Load content sample'),
                el(CardBody, null,
                    el(VStack, { spacing: 3 },
                        el(SelectControl, {
                            label: 'Content type',
                            value: contentType,
                            options: [
                                { label: 'page', value: 'page' },
                                { label: 'post', value: 'post' },
                                { label: 'attachment', value: 'attachment' },
                            ],
                            onChange: setContentType,
                        }),
                        el(TextControl, {
                            label: 'Content ID',
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
                            contentType,
                            onAddRule: setDraft,
                            rulesByPath,
                        }),
                    ),
                ),
            ),
            el(RuleEditor, {
                draft,
                onCancel: () => setDraft(null),
                onSave: handleSaveRule,
            }),
            el(Card, null,
                el(CardHeader, null, `Saved rules (${rules.length})`),
                el(CardBody, null,
                    rules.length === 0
                        ? el('em', null, 'No rules yet.')
                        : el('table', { className: 'widefat' },
                            el('thead', null,
                                el('tr', null,
                                    el('th', null, 'Content type'),
                                    el('th', null, 'Meta key'),
                                    el('th', null, 'Path'),
                                    el('th', null, 'Rule'),
                                    el('th', null, ''),
                                ),
                            ),
                            el('tbody', null,
                                rules.map((r) =>
                                    el('tr', { key: r.id },
                                        el('td', null, r.contentType),
                                        el('td', null, el('code', null, r.metaKey)),
                                        el('td', null, el('code', null, r.propertyPath || '(whole field)')),
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
