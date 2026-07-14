/**
 * Smoke tests: verify the Smartling job wizard renders correctly on both
 * the post edit page and the bulk submit page.
 */
const { test, expect } = require('@playwright/test');

const POST_ID = process.env.E2E_TEST_POST_ID || '1';

// Abort external browser requests before each test so that plugins adding
// external CSS/JS to admin page <head> sections don't block domcontentloaded
// in CI where those hosts are slow or unreachable. Localhost requests
// (including REST API calls made by Gutenberg) pass through untouched.
test.beforeEach(async ({ page }) => {
    await page.route(/^https?:\/\/(?!localhost)/, route => route.abort());
});

test.describe('Job wizard — post edit page', () => {
    test('#smartling-app container exists with non-empty data-nonce', async ({ page }) => {
        // PHP renders #smartling-app immediately; use 'attached' because the
        // Gutenberg block editor keeps the meta box section hidden until its
        // REST API calls complete (unrelated to the data-nonce we're verifying).
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 15000 });

        const nonce = await page.getAttribute('#smartling-app', 'data-nonce');
        expect(nonce, 'data-nonce attribute must be present and non-empty').toBeTruthy();
        expect(nonce.length, 'data-nonce must be at least 8 characters').toBeGreaterThanOrEqual(8);
    });

    test('#smartling-app has valid JSON in data-locales', async ({ page }) => {
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 15000 });

        const localesRaw = await page.getAttribute('#smartling-app', 'data-locales');
        expect(localesRaw, 'data-locales attribute must be present').toBeTruthy();

        let locales;
        expect(() => { locales = JSON.parse(localesRaw); }, 'data-locales must be valid JSON').not.toThrow();
        expect(Array.isArray(locales), 'data-locales must decode to an array').toBe(true);
    });

    test('React job wizard renders job tabs', async ({ page }) => {
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 15000 });

        // Wait for React to render the tab panel. The element may be inside a
        // Gutenberg meta box section that is initially hidden; toBeAttached and
        // toContainText both work on hidden elements (they use textContent, not
        // innerText, so they don't require the element to be visible).
        await expect(
            page.locator('#smartling-app [role="tablist"], #smartling-app .components-tab-panel__tabs').first(),
        ).toBeAttached({ timeout: 20000 });

        await expect(page.locator('#smartling-app')).toContainText('New Job');
        await expect(page.locator('#smartling-app')).toContainText('Existing Job');
    });
});

test.describe('Job wizard — bulk submit page', () => {
    test('#smartling-app container exists with non-empty data-nonce', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=smartling-bulk-submit', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 15000 });

        const nonce = await page.getAttribute('#smartling-app', 'data-nonce');
        expect(nonce, 'data-nonce attribute must be present and non-empty').toBeTruthy();
        expect(nonce.length).toBeGreaterThanOrEqual(8);
    });
});
