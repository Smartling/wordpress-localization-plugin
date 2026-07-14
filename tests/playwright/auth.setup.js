const { test, expect } = require('@playwright/test');
const path = require('path');

const authFile = path.join(__dirname, '.auth/admin.json');

test('login as admin', async ({ page }) => {
    const user = process.env.WP_ADMIN_USER || 'wp';
    const pass = process.env.WP_ADMIN_PASSWORD || 'wp';

    // Abort external browser requests: plugins add external CSS/JS to the login
    // page <head>; in CI those hosts are slow or unreachable, blocking
    // domcontentloaded for the full test timeout even though PHP serves the
    // complete HTML in ~2 s. Localhost requests pass through untouched.
    await page.route(/^https?:\/\/(?!localhost)/, route => route.abort());

    // 'commit' fires as soon as response headers arrive — we don't need all
    // scripts to run to fill a login form. One PHP-generated script (the
    // wp-i18n wrapper that injects translation data) may hang indefinitely in
    // CI; 'commit' lets us proceed the instant PHP starts sending the HTML.
    await page.goto('/wp-login.php', { waitUntil: 'commit' });
    // PHP delivers the full login form HTML in ~2 s; wait for the input to
    // appear in the DOM before trying to fill it.
    await page.waitForSelector('#user_login', { state: 'attached', timeout: 30000 });
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await page.click('#wp-submit');

    await expect(page).toHaveURL(/wp-admin/, { timeout: 15000 });

    await page.context().storageState({ path: authFile });
});
