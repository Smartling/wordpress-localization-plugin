const { render, createElement: el, useState, useEffect, useCallback } = wp.element;
const { Button, Card, CardBody, CardHeader, TabPanel, TextControl, TextareaControl, CheckboxControl, SelectControl, Spinner, Notice, Flex, __experimentalVStack: VStack } = wp.components;

function JobWizard({ isBulkSubmitPage, contentType, contentId, locales, ajaxUrl, adminUrl }) {
    const [activeTab, setActiveTab] = useState('new');
    const [jobs, setJobs] = useState([]);
    const [selectedJob, setSelectedJob] = useState('');
    const [jobName, setJobName] = useState('');
    const [description, setDescription] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [authorize, setAuthorize] = useState(false);
    const [selectedLocales, setSelectedLocales] = useState([]);
    const [depth, setDepth] = useState('0');
    const [relations, setRelations] = useState([]);
    const [selectedRelations, setSelectedRelations] = useState({});
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [pendingRequests, setPendingRequests] = useState(0);
    const [totalRequests, setTotalRequests] = useState(0);
    const [l1Relations, setL1Relations] = useState([]);
    const [l2Relations, setL2Relations] = useState([]);
    const [instantPolling, setInstantPolling] = useState(false);
    const [instantProgress, setInstantProgress] = useState(0);
    const [instantStatus, setInstantStatus] = useState('');
    const [instantSubmissionIds, setInstantSubmissionIds] = useState([]);
    const [instantCompletedCount, setInstantCompletedCount] = useState(0);

    const loadJobs = useCallback(async () => {
        try {
            const response = await jQuery.post(adminUrl, {
                action: 'smartling_job_api_proxy',
                innerAction: 'list-jobs',
                params: {}
            });
            if (response.status === 200) {
                setJobs(response.data);
            }
        } catch (e) {
            setError('Failed to load jobs');
        } finally {
            setLoading(false);
        }
    }, [adminUrl]);

    useEffect(() => {
        loadJobs();
    }, [loadJobs]);

    const loadRelations = useCallback(async (type, id, level = 1) => {
        const localeList = locales.map(l => l.blogId).join(',');
        const url = `${ajaxUrl}?action=smartling-get-relations&id=${id}&content-type=${type}&targetBlogIds=${localeList}`;

        setPendingRequests(prev => prev + 1);
        setTotalRequests(prev => prev + 1);

        try {
            const response = await jQuery.get(url);
            if (response.response?.data?.references) {
                const refs = response.response.data.references;
                if (level === 1) {
                    setL1Relations(prev => [...prev, ...refs.filter(r => !prev.some(p => p.contentType === r.contentType && p.id === r.id))]);
                } else {
                    setL2Relations(prev => [...prev, ...refs.filter(r => !prev.some(p => p.contentType === r.contentType && p.id === r.id))]);
                }
            }
        } finally {
            setPendingRequests(prev => prev - 1);
        }
    }, [ajaxUrl, locales]);

    useEffect(() => {
        if (depth === '0') return;

        if (isBulkSubmitPage) {
            jQuery('input.bulkaction[type=checkbox]:checked').each(function() {
                const parts = jQuery(this).attr('id').split('-');
                const id = parseInt(parts.shift());
                const type = parts.join('-');
                loadRelations(type, id, 1);
            });
        } else {
            loadRelations(contentType, contentId, 1);
        }
    }, [depth, isBulkSubmitPage, contentType, contentId, loadRelations]);

    useEffect(() => {
        if (depth === '2' && l1Relations.length > 0) {
            l1Relations.forEach(rel => loadRelations(rel.contentType, rel.id, 2));
        }
    }, [depth, l1Relations, loadRelations]);

    useEffect(() => {
        const allRels = depth === '1' ? l1Relations : depth === '2' ? [...l1Relations, ...l2Relations] : [];
        const unique = allRels.filter((r, i, arr) => arr.findIndex(x => x.contentType === r.contentType && x.id === r.id) === i);
        setRelations(unique);
    }, [depth, l1Relations, l2Relations]);

    const handleInstantTranslation = async () => {
        setSubmitting(true);
        setError('');
        setSuccess('');
        setInstantStatus('');
        setInstantCompletedCount(0);

        if (selectedLocales.length === 0) {
            setError('Please select at least one target locale.');
            setSubmitting(false);
            return;
        }

        const relationsData = {};
        selectedLocales.forEach(blogId => {
            relationsData[blogId] = {};
            relations.filter(r => selectedRelations[`${r.contentType}-${r.id}`]).forEach(r => {
                if (!relationsData[blogId][r.contentType]) {
                    relationsData[blogId][r.contentType] = [];
                }
                relationsData[blogId][r.contentType].push(r.id);
            });
        });

        const relatedItemsSet = new Set();
        relations.forEach(rel => {
            if (selectedRelations[`${rel.contentType}-${rel.id}`]) {
                relatedItemsSet.add(`${rel.contentType}-${rel.id}`);
            }
        });
        const itemCount = 1 + relatedItemsSet.size;
        const localeCount = selectedLocales.length;

        try {
            // Single AJAX call with relations
            const response = await jQuery.post(ajaxUrl, {
                action: 'smartling_instant_translation',
                contentType: contentType,
                contentId: contentId,
                targetBlogIds: selectedLocales,
                relations: relationsData
            });

            if (response.success && response.data?.submissionIds) {
                const submissionIds = response.data.submissionIds;
                setInstantSubmissionIds(submissionIds);
                setInstantPolling(true);
                setInstantProgress(5);
                setInstantStatus(`Translating ${itemCount} item${itemCount > 1 ? 's' : ''} to ${localeCount} locale${localeCount > 1 ? 's' : ''}...`);
                startInstantPolling(submissionIds, Date.now());
            } else {
                throw new Error(response.data?.message || 'Failed to start instant translation.');
            }
        } catch (e) {
            setError(e.message || 'Failed to start instant translation.');
            setSubmitting(false);
        }
    };

    const startInstantPolling = async (submissionIds, startTime, intervalIndex = 0) => {
        const POLL_INTERVALS = [1000, 2000, 4000, 8000, 16000];
        const MAX_BACKOFF = 30000;
        const TIMEOUT = 120000;

        const elapsed = Date.now() - startTime;

        if (elapsed >= TIMEOUT) {
            setInstantPolling(false);
            setError('Translation request timed out. Please check the submission status manually.');
            setSubmitting(false);
            return;
        }

        const progressPercent = Math.min(90, (elapsed / TIMEOUT) * 100);
        setInstantProgress(progressPercent);

        let completedCount = 0;
        let failedCount = 0;
        let hasError = false;
        let errorMessage = '';

        try {
            const statusPromises = submissionIds.map(submissionId =>
                jQuery.post(ajaxUrl, {
                    action: 'smartling_instant_translation_status',
                    submissionId: submissionId
                })
            );

            const responses = await Promise.all(statusPromises);

            responses.forEach((response, index) => {
                if (response.success && response.data) {
                    const status = response.data.status;

                    switch (status) {
                        case 'completed':
                            completedCount++;
                            break;

                        case 'failed':
                            failedCount++;
                            hasError = true;
                            errorMessage = response.data.message || 'Translation failed.';
                            break;
                    }
                }
            });

            setInstantCompletedCount(completedCount);

            if (submissionIds.length > 1) {
                setInstantStatus(`Translating ${submissionIds.length} items... ${completedCount} completed.`);
            }

            if (completedCount === submissionIds.length) {
                setInstantPolling(false);
                setInstantProgress(100);
                setSuccess(`All ${submissionIds.length} item${submissionIds.length > 1 ? 's' : ''} translated successfully!`);
                setSubmitting(false);
                return;
            }

            if (failedCount > 0 && (completedCount + failedCount) === submissionIds.length) {
                setInstantPolling(false);
                setError(`${failedCount} translation(s) failed. ${completedCount} completed successfully. ${errorMessage}`);
                setSubmitting(false);
                return;
            }

        } catch (e) {
            // Continue polling on error
        }

        const interval = intervalIndex < POLL_INTERVALS.length ? POLL_INTERVALS[intervalIndex] : MAX_BACKOFF;
        setTimeout(() => startInstantPolling(submissionIds, startTime, intervalIndex + 1), interval);
    };

    const handleSubmit = async () => {
        setSubmitting(true);
        setError('');
        setSuccess('');

        const blogIds = selectedLocales.join(',');
        const url = `${ajaxUrl}?action=smartling-create-submissions`;

        const data = {
            formAction: activeTab === 'clone' ? 'clone' : 'upload',
            source: { contentType, id: isBulkSubmitPage ? [] : [contentId] },
            job: {
                id: activeTab === 'new' ? '' : selectedJob,
                name: jobName,
                description,
                dueDate,
                timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                authorize
            },
            targetBlogIds: blogIds,
            relations: {}
        };

        selectedLocales.forEach(blogId => {
            data.relations[blogId] = {};
            relations.filter(r => selectedRelations[`${r.contentType}-${r.id}`]).forEach(r => {
                if (!data.relations[blogId][r.contentType]) {
                    data.relations[blogId][r.contentType] = [];
                }
                data.relations[blogId][r.contentType].push(r.id);
            });
        });

        if (isBulkSubmitPage) {
            data.ids = [];
            jQuery('input.bulkaction[type=checkbox]:checked').each(function() {
                const parts = jQuery(this).attr('id').split('-');
                data.ids.push(parseInt(parts.shift()));
                data.source.contentType = parts.join('-');
            });
        }

        try {
            if (activeTab === 'new') {
                const jobResponse = await jQuery.post(adminUrl, {
                    action: 'smartling_job_api_proxy',
                    innerAction: 'create-job',
                    params: {
                        jobName,
                        description,
                        dueDate,
                        locales: blogIds,
                        authorize,
                        timezone: data.job.timeZone
                    }
                });

                if (jobResponse.status >= 400) {
                    throw new Error(jobResponse.message?.global || 'Failed to create job');
                }
                data.job.id = jobResponse.data.translationJobUid;
            }

            const submissionResponse = await jQuery.post(url, data);
            if (submissionResponse.status && submissionResponse.status > 300) {
                throw new Error(submissionResponse.message?.global || 'Failed to add content to upload queue.');
            }
            setSuccess('Content successfully added to upload queue.');
        } catch (e) {
            setError(e.message || 'Failed adding content to upload queue.');
        } finally {
            setSubmitting(false);
        }
    };

    const progress = totalRequests > 0 ? ((totalRequests - pendingRequests) / totalRequests) * 100 : 0;

    if (loading) return el(Flex, { justify: 'center', style: { padding: '40px' } }, el(Spinner));

    return el('div', { style: { margin: '20px auto' } },
        el('style', {}, '#smartling-upload-widget-card div:first-child { height: auto; }'),
        el(Card, { id: 'smartling-upload-widget-card' },
        el(CardBody, {},
            el(TabPanel, {
                activeClass: 'is-active',
                onSelect: (tabName) => {
                    setActiveTab(tabName);
                    setError('');
                    setSuccess('');
                    setInstantStatus('');
                },
                tabs: [
                    { name: 'new', title: 'New Job' },
                    { name: 'existing', title: 'Existing Job' },
                    { name: 'instant', title: 'Instant Translation' },
                ]
            }, (tab) => el(VStack || 'div', { spacing: 4, style: { marginTop: '16px' } },
                error && el(Notice, { status: 'error', isDismissible: true, onRemove: () => setError('') }, error),
                success && el(Notice, { status: 'success', isDismissible: true, onRemove: () => setSuccess('') }, success),

                tab.name === 'existing' && el(SelectControl, {
                    label: 'Existing jobs',
                    value: selectedJob,
                    options: [{ label: 'Select a job', value: '' }, ...jobs.map(j => ({ label: j.jobName, value: j.translationJobUid }))],
                    onChange: (val) => {
                        setSelectedJob(val);
                        const job = jobs.find(j => j.translationJobUid === val);
                        if (job) {
                            setJobName(job.jobName);
                            setDescription(job.description || '');
                            setDueDate(job.dueDate ? new Date(job.dueDate).toISOString().slice(0, 16) : '');
                            setSelectedLocales((job.targetLocaleIds || [])
                                .map(localeCode => locales.find(l => l.smartlingLocale === localeCode)?.blogId));
                        }
                    }
                }),

                tab.name === 'instant' && el('div', { style: { padding: '12px', background: '#f0f0f1', borderRadius: '4px', marginBottom: '16px' } },
                    el('p', { style: { margin: 0, fontSize: '14px' } },
                        el('strong', {}, 'Instant Translation'),
                        ' provides immediate translation without creating a job.'
                    )
                ),

                tab.name === 'instant' && instantPolling && el('div', { style: { padding: '16px', background: '#f0f0f1', borderRadius: '4px', marginBottom: '16px' } },
                    el('div', { style: { marginBottom: '12px', fontSize: '14px', fontWeight: 500 } }, instantStatus),
                    el('div', { style: { background: '#fff', borderRadius: '4px', overflow: 'hidden', height: '20px' } },
                        el('div', { style: { background: '#2271b1', height: '100%', width: `${instantProgress}%`, transition: 'width 0.3s' } })
                    )
                ),

                tab.name !== 'clone' && tab.name !== 'instant' && el('div', {},
                    tab.name === 'new' && el(TextControl, { label: 'Name', value: jobName, onChange: setJobName }),
                    el(TextareaControl, { label: 'Description', value: description, onChange: setDescription, rows: 3 }),
                    el(TextControl, {
                        label: 'Due Date',
                        type: 'datetime-local',
                        value: dueDate,
                        onChange: setDueDate,
                        placeholder: '2025-12-31T23:59'
                    }),
                    el(CheckboxControl, { label: 'Authorize Job', checked: authorize, onChange: setAuthorize })
                ),

                (tab.name === 'instant' || tab.name !== 'clone') && el('div', {},
                    el('fieldset', { style: { marginTop: '16px', border: '1px solid #ddd', padding: '12px', borderRadius: '4px' } },
                        el('legend', { style: { fontWeight: 600, padding: '0 8px' } }, 'Target Locales'),
                        el('div', { style: { display: 'flex', gap: '8px', marginBottom: '8px' } },
                            el(Button, {
                                variant: 'secondary',
                                size: 'small',
                                onClick: () => setSelectedLocales(locales.map(l => l.blogId))
                            }, 'Check All'),
                            el(Button, {
                                variant: 'secondary',
                                size: 'small',
                                onClick: () => setSelectedLocales([])
                            }, 'Uncheck All')
                        ),
                        el('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '12px' } },
                            locales.map(locale => el(CheckboxControl, {
                                key: locale.blogId,
                                label: locale.label,
                                checked: selectedLocales.includes(locale.blogId),
                                onChange: (checked) => {
                                    setSelectedLocales(prev => checked ? [...prev, locale.blogId] : prev.filter(id => id !== locale.blogId));
                                }
                            }))
                        )
                    ),

                    el(SelectControl, {
                        label: 'Related content',
                        value: depth,
                        options: [
                            { label: "Don't send related content", value: '0' },
                            { label: 'Send related content one level deep', value: '1' },
                            { label: 'Send related content two levels deep', value: '2' }
                        ],
                        onChange: (val) => {
                            setDepth(val);
                            if (val === '0') {
                                setL1Relations([]);
                                setL2Relations([]);
                                setPendingRequests(0);
                                setTotalRequests(0);
                            }
                        }
                    }),

                    pendingRequests > 0 && el('div', { style: { margin: '16px 0', padding: '12px', background: '#f0f0f0', borderRadius: '4px' } },
                        el('div', { style: { marginBottom: '8px', fontSize: '13px', fontWeight: 500 } },
                            `Loading relations: ${totalRequests - pendingRequests} of ${totalRequests} completed`
                        ),
                        el('div', { style: { background: '#fff', borderRadius: '4px', overflow: 'hidden', height: '8px' } },
                            el('div', { style: { background: '#2271b1', height: '100%', width: `${progress}%`, transition: 'width 0.3s' } })
                        )
                    ),

                    relations.length > 0 && el('fieldset', { style: { marginTop: '16px', border: '1px solid #ddd', padding: '12px', borderRadius: '4px' } },
                        el('legend', { style: { fontWeight: 600, padding: '0 8px' } }, 'Related content to be uploaded'),
                        el('div', { style: { maxHeight: '200px', overflowY: 'auto' } },
                            relations.map(rel => {
                                const key = `${rel.contentType}-${rel.id}`;
                                const titleLink = rel.url ? 
                                    el('a', { href: rel.url, target: '_blank', style: { textDecoration: 'none' }, onClick: (e) => e.stopPropagation() }, rel.title || 'Untitled') : 
                                    (rel.title || 'Untitled');
                                const thumbnail = rel.contentType === 'attachment' && rel.thumbnailUrl ?
                                    el('img', { 
                                        src: rel.thumbnailUrl, 
                                        alt: 'Preview', 
                                        style: { width: '30px', height: '30px', objectFit: 'cover', verticalAlign: 'middle', marginLeft: '5px' }
                                    }) : null;
                                
                                return el('label', { key, style: { display: 'flex', alignItems: 'center', marginBottom: '8px', cursor: 'pointer' } },
                                    el('input', {
                                        type: 'checkbox',
                                        checked: selectedRelations[key] || false,
                                        onChange: (e) => setSelectedRelations(prev => ({ ...prev, [key]: e.target.checked })),
                                        style: { marginRight: '8px' }
                                    }),
                                    el('span', {},
                                        `${rel.contentType} #${rel.id} (${rel.status}) - `,
                                        titleLink,
                                        thumbnail
                                    )
                                );
                            })
                        )
                    )
                ),

                el(Flex, { justify: 'center', style: { marginTop: '24px', paddingTop: '16px', borderTop: '1px solid #ddd' } },
                    el(Button, {
                        variant: 'primary',
                        isBusy: submitting,
                        disabled: submitting || pendingRequests > 0 || selectedLocales.length === 0,
                        onClick: tab.name === 'instant' ? handleInstantTranslation : handleSubmit
                    }, tab.name === 'instant' ? 'Request Instant Translation' : tab.name === 'new' ? 'Create Job' : tab.name === 'clone' ? 'Clone' : 'Add to selected Job')
                )
            ))
        )
    ));
}

if (document.getElementById('smartling-app')) {
    const container = document.getElementById('smartling-app');
    const isBulkSubmitPage = container.dataset.bulkSubmit === 'true';
    const contentType = container.dataset.contentType || '';
    const contentId = parseInt(container.dataset.contentId) || 0;
    const locales = JSON.parse(container.dataset.locales || '[]');
    const ajaxUrl = container.dataset.ajaxUrl || '';
    const adminUrl = container.dataset.adminUrl || '';

    render(
        el(JobWizard, { isBulkSubmitPage, contentType, contentId, locales, ajaxUrl, adminUrl }),
        container
    );
}

