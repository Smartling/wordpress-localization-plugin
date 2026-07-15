/**
 * Core security test: verifies every admin-ajax.php POST includes _wpnonce
 * and that no admin-ajax.php call returns 403.
 *
 * This test would have caught the WP-1007 regression where legacy jQuery in
 * ContentEditJob.php omitted _wpnonce from its smartling_job_api_proxy calls.
 */
const { test, expect } = require('@playwright/test');

const POST_ID = process.env.E2E_TEST_POST_ID || '1';

/**
 * Collects AJAX observations (nonce presence + response status) for every
 * admin-ajax.php request made while the callback runs.
 *
 * Callbacks navigate with waitUntil:'commit' (first response byte from PHP).
 * After 'commit', we explicitly wait for DOMContentLoaded so all deferred
 * scripts have executed before starting the drain window. The e2e-fast-http
 * mu-plugin blocks external PHP HTTP calls, cutting PHP execution from 90 s+
 * to < 1 s so DOMContentLoaded now fires in ~15-30 s in CI. Once
 * DOMContentLoaded fires, React's loadJobs() useEffect has been scheduled
 * and the first admin-ajax.php POST is either in-flight or imminent.
 */
async function collectAjaxObservations(page, callback) {
    const observations = [];
    const requestToIdx = new Map();
    let pendingCount = 0;

    const onRequest = (request) => {
        if (!request.url().includes('admin-ajax.php') || request.method() !== 'POST') return;

        const body = request.postData() || '';
        const urlParams = new URLSearchParams(request.url().split('?')[1] || '');
        const action =
            new URLSearchParams(body).get('action') ||
            urlParams.get('action') ||
            '(unknown)';

        const idx = observations.push({
            action,
            hasNonce: body.includes('_wpnonce') || request.url().includes('_wpnonce'),
            status: null,
        }) - 1;

        requestToIdx.set(request, idx);
        pendingCount++;
    };

    const onResponse = (response) => {
        if (!response.url().includes('admin-ajax.php')) return;
        const idx = requestToIdx.get(response.request());
        if (idx !== undefined) {
            observations[idx].status = response.status();
            requestToIdx.delete(response.request());
            pendingCount = Math.max(0, pendingCount - 1);
        }
    };

    page.on('request', onRequest);
    page.on('response', onResponse);

    try {
        await callback();
        // 'commit' fires when PHP sends the first response byte. Wait for the
        // PHP-rendered #smartling-app element to appear in the DOM (body arrived).
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 90000 });
        // Wait for DOMContentLoaded so that all deferred scripts (Gutenberg,
        // app.js) have executed. With the e2e-fast-http mu-plugin blocking
        // external PHP HTTP calls, DOMContentLoaded now fires in ~15-30 s.
        // After it fires, React's loadJobs() useEffect has been scheduled and
        // the first admin-ajax.php POST is either in-flight or about to be.
        // The catch handles the rare case where DOMContentLoaded is delayed
        // beyond 60 s — the callDeadline loop below still drains any calls.
        await page.waitForLoadState('domcontentloaded', { timeout: 60000 }).catch(() => {});
        // Wait for at least one admin-ajax.php POST to be dispatched.
        const callDeadline = Date.now() + 30000;
        while (pendingCount === 0 && Date.now() < callDeadline) {
            await page.waitForTimeout(200);
        }
        // Drain in-flight AJAX requests (they were dispatched during page load
        // and should complete in well under 8s; we don't wait for unrelated
        // background requests like WordPress heartbeat).
        const deadline = Date.now() + 12000;
        while (pendingCount > 0 && Date.now() < deadline) {
            await page.waitForTimeout(100);
        }
    } finally {
        page.off('request', onRequest);
        page.off('response', onResponse);
    }

    return observations;
}

// Abort requests that would saturate PHP workers without benefiting the tests.
//
// External hosts (CDNs, Elementor, etc.) — aborted: slow/unreachable in CI.
//
// REST API (/wp-json/) — aborted: Gutenberg makes 20-30 async REST calls per
// page load. Each occupies a PHP worker for 5-30 s. Aborting at the browser
// prevents those workers from being occupied while subsequent tests navigate.
//
// admin-ajax.php is NOT aborted: these calls are exactly what we are testing.
test.beforeEach(async ({ page }) => {
    await page.route(/^https?:\/\/(?!localhost)/, route => route.abort());
    await page.route(/\/wp-json\//, route => route.abort());
});

test.describe('AJAX security — post edit page', () => {
    test('all admin-ajax POSTs include _wpnonce', async ({ page }) => {
        const observations = await collectAjaxObservations(page, async () => {
            await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'commit' });
        });

        const smartlingCalls = observations.filter((o) =>
            o.action.startsWith('smartling'),
        );

        expect(
            smartlingCalls.length,
            'Expected at least one Smartling AJAX call on post edit page',
        ).toBeGreaterThan(0);

        const missing = smartlingCalls.filter((o) => !o.hasNonce);
        expect(
            missing,
            `These Smartling AJAX calls are missing _wpnonce: ${JSON.stringify(missing)}`,
        ).toHaveLength(0);
    });

    test('no 403 responses from admin-ajax.php', async ({ page }) => {
        const observations = await collectAjaxObservations(page, async () => {
            await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'commit' });
        });

        const forbidden = observations.filter((o) => o.status === 403);
        expect(
            forbidden,
            `Got 403 on these AJAX calls: ${JSON.stringify(forbidden)}`,
        ).toHaveLength(0);
    });
});

test.describe('AJAX security — bulk submit page', () => {
    test('all admin-ajax POSTs include _wpnonce', async ({ page }) => {
        const observations = await collectAjaxObservations(page, async () => {
            await page.goto('/wp-admin/admin.php?page=smartling-bulk-submit', { waitUntil: 'commit' });
        });

        const smartlingCalls = observations.filter((o) =>
            o.action.startsWith('smartling'),
        );

        // Bulk submit page may not trigger AJAX on load — only assert when calls exist
        const missing = smartlingCalls.filter((o) => !o.hasNonce);
        expect(
            missing,
            `These Smartling AJAX calls are missing _wpnonce: ${JSON.stringify(missing)}`,
        ).toHaveLength(0);
    });

    test('no 403 responses from admin-ajax.php', async ({ page }) => {
        const observations = await collectAjaxObservations(page, async () => {
            await page.goto('/wp-admin/admin.php?page=smartling-bulk-submit', { waitUntil: 'commit' });
        });

        const forbidden = observations.filter((o) => o.status === 403);
        expect(
            forbidden,
            `Got 403 on these AJAX calls: ${JSON.stringify(forbidden)}`,
        ).toHaveLength(0);
    });
});
