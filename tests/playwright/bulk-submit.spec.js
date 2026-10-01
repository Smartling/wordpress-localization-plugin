/**
 * Integration test for the bulk-submit "create submissions" flow.
 *
 * Regression coverage: bulk submit used to always fail with
 *   {"status":"FAILED","response":{"key":"content.submission.failed",
 *    "message":"Source content id is empty, please save content prior to uploading"}}
 * because the bulk-submit UI sends an empty `source.id` array (the selected
 * content ids travel in `ids` instead), while
 * UserTranslationRequest::fromArray() unconditionally required `source.id[0]`
 * before ever looking at `ids`/isBulk(). See UserTranslationRequest::fromArray()
 * and UserCloneRequest::getSourceId().
 *
 * This test drives the real bulk-submit page end to end (select a content row,
 * create a job, submit) and inspects the actual admin-ajax.php network traffic,
 * rather than asserting on rendered UI text — the front end swallows the
 * server's error details (jQuery rejects on the AJAX call's HTTP 400 and the
 * catch handler falls back to a generic message), so the network response body
 * is the only place the regression signature is visible.
 */
const { test, expect } = require('@playwright/test');

// Real calls to the Smartling API (job creation, batch creation) plus a
// possibly-cold React mount (see job-wizard.spec.js) can comfortably exceed
// the default 120 s test timeout.
test.setTimeout(240000);

test.describe('Bulk submit — create submissions', () => {
    test('submitting a bulk selection does not fail with "Source content id is empty"', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=smartling-bulk-submit', { waitUntil: 'commit' });
        await page.waitForSelector('#smartling-app', { state: 'attached', timeout: 90000 });

        const app = page.locator('#smartling-app');

        // Wait for the React job wizard to mount (tabs rendered) and for the
        // bulk-submit table rows (with their per-row checkboxes) to be present.
        await page.waitForFunction(
            () => {
                const el = document.getElementById('smartling-app');
                return el && (
                    el.querySelector('[role="tablist"]') !== null ||
                    el.querySelector('.components-tab-panel__tabs') !== null
                );
            },
            null,
            { timeout: 90000 },
        );
        const rowCheckbox = page.locator('input.bulkaction[type="checkbox"]').first();
        await expect(rowCheckbox, 'Bulk submit table must list at least one content row to select').toBeVisible({ timeout: 30000 });

        // Checkbox id is "{contentId}-{contentType}" (see BulkSubmitTableWidget::column_cb()).
        const checkboxId = await rowCheckbox.getAttribute('id');
        const [expectedContentId] = checkboxId.split('-');
        await rowCheckbox.check();

        // Default tab is "New Job" — fill the required Name field.
        await app.getByLabel('Name', { exact: true }).fill(`Playwright bulk submit ${Date.now()}`);

        // Select a target locale (submit button stays disabled without one).
        const targetLocalesFieldset = app.locator('fieldset', { hasText: 'Target Locales' });
        const localeCheckbox = targetLocalesFieldset.locator('input[type="checkbox"]').first();
        await expect(localeCheckbox, 'Profile must have at least one enabled target locale').toBeVisible({ timeout: 15000 });
        await localeCheckbox.check();

        const isCreateSubmissions = (url, body) =>
            url.includes('admin-ajax.php') && url.includes('action=smartling-create-submissions');
        const isCreateJob = (url, body) =>
            url.includes('admin-ajax.php') && (body || '').includes('innerAction=create-job');

        const [jobResponse, submissionResponse] = await Promise.all([
            page.waitForResponse((r) => isCreateJob(r.url(), r.request().postData()), { timeout: 120000 }),
            page.waitForResponse((r) => isCreateSubmissions(r.url(), r.request().postData()), { timeout: 120000 }),
            app.getByRole('button', { name: 'Create Job' }).click(),
        ]);

        const jobBody = await jobResponse.json();
        expect(jobBody.status, `Job creation failed: ${JSON.stringify(jobBody)}`).toBe(200);

        const submissionRequestBody = submissionResponse.request().postData() || '';
        const submissionParams = new URLSearchParams(submissionRequestBody);
        expect(
            submissionParams.getAll('ids[]'),
            'Bulk submit must send the selected content id via `ids[]`',
        ).toContain(expectedContentId);
        expect(
            submissionParams.has('source[id][]'),
            'Bulk submit must NOT send a populated source.id — bulk content ids travel in `ids` only',
        ).toBe(false);

        const submissionBody = await submissionResponse.json();

        // The exact regression: bulk submit must never fail because source
        // content id is considered empty.
        if (submissionBody.status === 'FAILED') {
            expect(
                submissionBody.response?.message,
                `Regression: bulk submit failed with the "empty source id" error: ${JSON.stringify(submissionBody)}`,
            ).not.toContain('Source content id is empty');
        }

        expect(submissionResponse.status(), `Unexpected AJAX status, body: ${JSON.stringify(submissionBody)}`).toBe(200);
        expect(submissionBody.status, `Unexpected response body: ${JSON.stringify(submissionBody)}`).toBe('SUCCESS');
    });
});
