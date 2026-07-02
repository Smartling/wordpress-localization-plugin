/**
 * Smoke tests: verify the Smartling job wizard renders correctly on both
 * the post edit page and the bulk submit page.
 */
const { test, expect } = require('@playwright/test');

const POST_ID = process.env.E2E_TEST_POST_ID || '1';

test.describe('Job wizard — post edit page', () => {
    test('#smartling-app container exists with non-empty data-nonce', async ({ page }) => {
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`);
        await page.waitForSelector('#smartling-app', { timeout: 10000 });

        const nonce = await page.getAttribute('#smartling-app', 'data-nonce');
        expect(nonce, 'data-nonce attribute must be present and non-empty').toBeTruthy();
        expect(nonce.length, 'data-nonce must be at least 8 characters').toBeGreaterThanOrEqual(8);
    });

    test('#smartling-app has valid JSON in data-locales', async ({ page }) => {
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`);
        await page.waitForSelector('#smartling-app');

        const localesRaw = await page.getAttribute('#smartling-app', 'data-locales');
        expect(localesRaw, 'data-locales attribute must be present').toBeTruthy();

        let locales;
        expect(() => { locales = JSON.parse(localesRaw); }, 'data-locales must be valid JSON').not.toThrow();
        expect(Array.isArray(locales), 'data-locales must decode to an array').toBe(true);
    });

    test('React job wizard renders job tabs', async ({ page }) => {
        await page.goto(`/wp-admin/post.php?post=${POST_ID}&action=edit`);
        await page.waitForSelector('#smartling-app');

        // Wait for React to hydrate and render the tab panel
        await expect(
            page.locator('#smartling-app [role="tablist"], #smartling-app .components-tab-panel__tabs'),
        ).toBeVisible({ timeout: 15000 });

        await expect(page.locator('#smartling-app')).toContainText('New Job');
        await expect(page.locator('#smartling-app')).toContainText('Existing Job');
    });
});

test.describe('Job wizard — bulk submit page', () => {
    test('#smartling-app container exists with non-empty data-nonce', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=smartling-bulk-submit');
        await page.waitForSelector('#smartling-app', { timeout: 10000 });

        const nonce = await page.getAttribute('#smartling-app', 'data-nonce');
        expect(nonce, 'data-nonce attribute must be present and non-empty').toBeTruthy();
        expect(nonce.length).toBeGreaterThanOrEqual(8);
    });
});
