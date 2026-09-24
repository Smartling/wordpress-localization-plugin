/**
 * Regression test for WP-1019: unlocking fields via the Translation Lock
 * popup silently failed to save.
 *
 * Root cause: TranslationLock.php rendered its own CSRF nonce field via
 * wp_nonce_field(), and TranslationLockTableWidget::display() (inherited from
 * WP_List_Table) *also* renders a hidden `_wpnonce` field for its own (unused)
 * bulk actions. Both hidden inputs shared the name `_wpnonce`, so the browser
 * submitted two identically-named fields and PHP kept only the last one in
 * $_POST — the list table's value, which never matches the
 * 'smartling-translation-lock-action' nonce action. handleFormPost() silently
 * rejected every save (logs a warning, returns) and the popup re-rendered the
 * unchanged database state, so unlocking looked like a no-op.
 *
 * This test drives the real popup end to end: opens the Translation Lock
 * thickbox on a post that is seeded (via create-locked-submission.php) with
 * "Lock all fields" and one individual field locked, unchecks both, saves,
 * and asserts the popup reloads showing them unchecked rather than reverting.
 */
const { test, expect } = require('@playwright/test');

const TARGET_BLOG_PATH = process.env.E2E_LOCK_TARGET_BLOG_PATH;
const TARGET_POST_ID = process.env.E2E_LOCK_TARGET_POST_ID;

test.skip(
    !TARGET_BLOG_PATH || !TARGET_POST_ID,
    'E2E_LOCK_TARGET_BLOG_PATH/E2E_LOCK_TARGET_POST_ID not set — run create-locked-submission.php first',
);

test.describe('Translation Lock screen', () => {
    test('unchecking "Lock all fields" and a field lock actually persists after Save', async ({ page }) => {
        await page.goto(`/${TARGET_BLOG_PATH}/wp-admin/post.php?post=${TARGET_POST_ID}&action=edit`, {
            waitUntil: 'commit',
        });
        console.log(`[translation-lock] navigated to: ${page.url()}`);

        // A first-time-per-user "Welcome to the editor" guide overlays the
        // whole screen and blocks every click until dismissed. Only shown
        // once per user (persisted server-side), but the E2E DB is fresh each
        // run, so it reliably appears here. Escape (not a click) dismisses
        // it: clicking its own Close button is flaky because the guide can
        // still be animating in, and the modal's own overlay div briefly
        // intercepts pointer events aimed at content inside it.
        //
        // The guide can take a while to mount (Gutenberg/React bootstrap on a
        // cold OPcache), so this needs a generous window - a short one here
        // just means we "miss" it and it pops up moments later, right as we
        // try to click the lock link, which blocks that click for the rest
        // of the test's timeout (Playwright retries the click, but nothing
        // ever dismisses the now-static overlay).
        const guideOverlay = page.locator('.components-modal__screen-overlay').first();
        const dismissWelcomeGuideIfPresent = async () => {
            try {
                await guideOverlay.waitFor({ state: 'visible', timeout: 30000 });
                await page.keyboard.press('Escape');
                await guideOverlay.waitFor({ state: 'hidden', timeout: 10000 });
                return true;
            } catch {
                return false; // Guide never appeared (already dismissed for this user).
            }
        };
        await dismissWelcomeGuideIfPresent();

        const lockLink = page.locator('a.thickbox', { hasText: 'Translation lock' });
        try {
            await expect(lockLink, 'Translation Lock meta box link must be present on a locked submission\'s target post').toBeVisible({ timeout: 30000 });
        } catch (err) {
            // Diagnostic for CI-only failures: which page did we actually end up
            // on? (This test is the only spec that navigates to a *subsite's*
            // admin via a path prefix, e.g. /es/wp-admin/..., rather than the
            // main site's /wp-admin/ directly.)
            console.log(`[translation-lock] lock link not found; final url: ${page.url()}, title: ${await page.title().catch(() => '?')}`);
            throw err;
        }
        try {
            await lockLink.click({ timeout: 15000 });
        } catch (err) {
            // Defense in depth against the guide appearing in the narrow race
            // window between the check above and this click.
            console.log('[translation-lock] click intercepted, checking for a late-appearing welcome guide');
            const dismissed = await dismissWelcomeGuideIfPresent();
            if (!dismissed) {
                throw err;
            }
            await lockLink.click();
        }

        // Thickbox opens the popup in an iframe; wait for it to attach and load.
        await page.waitForSelector('#TB_iframeContent', { timeout: 30000 });
        const frame = await waitForPopupFrame(page);
        await frame.waitForSelector('#locked_page', { timeout: 30000 });

        // Fixture seeds the submission as locked — confirm the popup reflects that
        // before we touch anything, otherwise the test proves nothing.
        expect(await frame.isChecked('#locked_page'), 'Fixture must seed "Lock all fields" as checked').toBe(true);
        const fieldLockSelector = 'input.field_lock_element[name="lockField[entity/post_title]"]';
        expect(await frame.isChecked(fieldLockSelector), 'Fixture must seed the post_title field lock as checked').toBe(true);

        await frame.uncheck('#locked_page');
        await frame.uncheck(fieldLockSelector);

        await Promise.all([
            frame.waitForNavigation({ timeout: 30000 }),
            frame.click('#submit'),
        ]);

        await frame.waitForSelector('#locked_page', { timeout: 30000 });
        expect(
            await frame.isChecked('#locked_page'),
            'Regression WP-1019: "Lock all fields" must stay unchecked after Save, not revert',
        ).toBe(false);
        expect(
            await frame.isChecked(fieldLockSelector),
            'Regression WP-1019: individual field lock must stay unchecked after Save, not revert',
        ).toBe(false);
    });
});

/**
 * The thickbox iframe is attached to the DOM before its document (from
 * admin-post.php) finishes loading. Poll page.frame() rather than relying on
 * a single 'frameattached'/'framenavigated' event, since the popup may have
 * already loaded by the time a listener would be registered.
 */
async function waitForPopupFrame(page) {
    const deadline = Date.now() + 30000;
    while (Date.now() < deadline) {
        const frame = page.frame({ url: /smartling_translation_lock_popup/ });
        if (frame) {
            return frame;
        }
        await page.waitForTimeout(200);
    }
    throw new Error('Translation Lock popup iframe never attached');
}
