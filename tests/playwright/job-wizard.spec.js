/**
 * Smoke tests: verify the Smartling job wizard renders correctly on both
 * the post edit page and the bulk submit page.
 */
const { test, expect } = require('@playwright/test');

const POST_ID = process.env.E2E_TEST_POST_ID || '1';

// Abort requests that would saturate PHP workers without benefiting the tests.
//
// External hosts — aborted: slow/unreachable in CI.
//
// REST API (/wp-json/) — aborted: Gutenberg async calls that occupy PHP workers
// without affecting the DOM attributes (#smartling-app data-nonce/data-locales)
// or the React tab structure we are testing.
//
// admin-ajax.php — aborted: Smartling API lookups that can take 30-300 s per
// call. Aborting prevents worker saturation so subsequent page.goto calls are
// not queued behind outstanding calls from earlier tests. All DOM attributes
// checked in these tests are PHP-rendered and do not require AJAX responses.
test.beforeEach(async ({ page }) => {
    await page.route(/^https?:\/\/(?!localhost)/, route => route.abort());
    await page.route(/\/wp-json\//, route => route.abort());
    await page.route(/\/wp-admin\/admin-ajax\.php/, route => route.abort());
});

test.describe('Job wizard — post edit page', () => {
    test('#smartling-app container exists with non-empty data-nonce', async ({ page }) => {
        // PHP renders #smartling-app immediately; use 'attached' because the
        // Gutenberg block editor keeps the meta box section hidden until its
        // REST API calls complete (unrelated to the data-nonce we're verifying).
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'commit' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 90000 });

        const nonce = await page.getAttribute('#smartling-app', 'data-nonce');
        expect(nonce, 'data-nonce attribute must be present and non-empty').toBeTruthy();
        expect(nonce.length, 'data-nonce must be at least 8 characters').toBeGreaterThanOrEqual(8);
    });

    test('#smartling-app has valid JSON in data-locales', async ({ page }) => {
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'commit' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 90000 });

        const localesRaw = await page.getAttribute('#smartling-app', 'data-locales');
        expect(localesRaw, 'data-locales attribute must be present').toBeTruthy();

        let locales;
        expect(() => { locales = JSON.parse(localesRaw); }, 'data-locales must be valid JSON').not.toThrow();
        expect(Array.isArray(locales), 'data-locales must decode to an array').toBe(true);
    });

    test('React job wizard renders job tabs', async ({ page }) => {
        // Allow extra time: waitForSelector (90 s) + React mount (up to 90 s)
        // can exceed the global 120 s when multiple cold PHP workers are hit.
        test.setTimeout(240000);

        const pageErrors = [];
        page.on('pageerror', (err) => pageErrors.push({
            message: err.message || String(err),
            stack: (err.stack || '').substring(0, 200),
        }));

        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'commit' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 90000 });

        // Diagnostic: log the state of #smartling-app immediately after it
        // attaches so CI logs show whether React mounted (spinner/tablist) or
        // the container is empty (app.js not yet executed or threw an error).
        const diag = await page.evaluate(() => {
            const el = document.getElementById('smartling-app');
            return {
                children: el ? el.childElementCount : -1,
                hasTablist: !!el?.querySelector('[role="tablist"]'),
                hasSpinner: !!el?.querySelector('[class*="spinner"], .components-spinner'),
                wpElementRender: typeof wp?.element?.render,
                innerHTML100: el ? (el.innerHTML || '').substring(0, 100) : '',
            };
        });
        console.log('[E2E DIAG react-tabs]', JSON.stringify({ diag, pageErrors }));

        // Wait for React to mount and render the tab panel. app.js is a footer
        // script that executes after all Gutenberg/Elementor scripts; on a cold
        // PHP worker (each of the 16 workers has its own OPcache) this can take
        // 30-80 s. Once app.js runs, React mounts synchronously and loadJobs()
        // fires via useEffect — admin-ajax.php is aborted by beforeEach so the
        // fetch rejects immediately and setLoading(false) renders the tabs within
        // milliseconds of app.js executing.
        //
        // page.waitForFunction uses the browser's native querySelector, which is
        // the same mechanism as the page.evaluate() diagnostic above — avoids
        // any Playwright CSS selector-list parsing ambiguity that could cause a
        // compound 'a, b' locator to not find what querySelector finds directly.
        await page.waitForFunction(
            () => {
                const app = document.getElementById('smartling-app');
                return app && (
                    app.querySelector('[role="tablist"]') !== null ||
                    app.querySelector('.components-tab-panel__tabs') !== null
                );
            },
            null,
            { timeout: 90000 },
        );

        await expect(page.locator('#smartling-app')).toContainText('New Job');
        await expect(page.locator('#smartling-app')).toContainText('Existing Job');
    });
});

test.describe('Job wizard — bulk submit page', () => {
    test('#smartling-app container exists with non-empty data-nonce', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=smartling-bulk-submit', { waitUntil: 'commit' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 90000 });

        const nonce = await page.getAttribute('#smartling-app', 'data-nonce');
        expect(nonce, 'data-nonce attribute must be present and non-empty').toBeTruthy();
        expect(nonce.length).toBeGreaterThanOrEqual(8);
    });
});
