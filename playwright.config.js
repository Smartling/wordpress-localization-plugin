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
    timeout: 45000,
    // Sequential execution prevents PHP worker starvation: concurrent admin page
    // loads each trigger multiple PHP requests (REST API, admin-ajax.php). With 4
    // PHP workers and 2+ simultaneous Playwright tests, all workers can saturate
    // and the page 'load' event never fires within the test timeout.
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['line'],
        ['junit', { outputFile: 'tests/playwright-results.xml' }],
    ],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://test.com',
        headless: true,
        screenshot: 'only-on-failure',
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
