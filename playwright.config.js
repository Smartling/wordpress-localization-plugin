// @ts-check
const { defineConfig, devices } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// Auto-load .env.playwright if present so `npx playwright test` works without
// manually sourcing the file first.
const envFile = path.join(__dirname, '.env.playwright');
if (fs.existsSync(envFile)) {
    for (const line of fs.readFileSync(envFile, 'utf8').split('\n')) {
        const match = line.match(/^([A-Z_][A-Z0-9_]*)=(.+)$/);
        if (match && process.env[match[1]] === undefined) {
            process.env[match[1]] = match[2].trim();
        }
    }
}

module.exports = defineConfig({
    testDir: 'tests/playwright',
    timeout: 120000,
    // test.sh runs PHP_CLI_SERVER_WORKERS=16 - oversubscribed relative to
    // these 4 Playwright workers on purpose, since a single admin page load
    // fires many concurrent sub-requests (CSS, JS, ajax) on its own; matching
    // PHP workers 1:1 to Playwright workers starved every page load under
    // real concurrent load (observed as intermittent CI timeouts at
    // different points each run - a resource-contention signature).
    workers: 4,
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['line'],
        ['junit', { outputFile: 'tests/playwright-results.xml' }],
    ],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://test.com',
        headless: true,
        screenshot: { mode: 'only-on-failure', fullPage: true },
        video: 'off',
    },
    projects: [
        {
            name: 'setup',
            testMatch: '**/auth.setup.js',
        },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'tests/playwright/.auth/admin.json',
            },
            dependencies: ['setup'],
        },
    ],
});
