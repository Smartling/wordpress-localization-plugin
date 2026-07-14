const { test, expect } = require('@playwright/test');
const path = require('path');

const authFile = path.join(__dirname, '.auth/admin.json');

test('login as admin', async ({ page }) => {
    const user = process.env.WP_ADMIN_USER || 'wp';
    const pass = process.env.WP_ADMIN_PASSWORD || 'wp';

    // Abort ALL static resource requests (scripts, stylesheets, fonts, images).
    // The login form is pure HTML and submits via a standard POST — no JavaScript
    // or CSS is needed to fill the form or click the submit button. Aborting
    // static resources prevents a PHP bootstrap race condition: wp server
    // bootstraps WordPress for every request (including .min.js files); with
    // 16 workers all starting concurrently, several bootstraps race to check
    // plugin update transients, triggering slow external HTTP calls (30-90 s)
    // that block the browser's HTML parser and keep #user_login out of the DOM.
    await page.route(/\.(js|css|woff2?|ttf|eot|svg|png|gif|ico)(\?.*)?$/i, route => route.abort());

    // 'commit' fires as soon as response headers arrive — we don't need scripts
    // to execute to fill a login form.
    await page.goto('/wp-login.php', { waitUntil: 'commit' });
    // PHP delivers the full login form HTML in ~2 s; wait for the input to
    // appear in the DOM before trying to fill it. With static resources aborted
    // the HTML parser is never blocked, so the element appears almost immediately.
    await page.waitForSelector('#user_login', { state: 'attached', timeout: 15000 });
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await page.click('#wp-submit');

    await expect(page).toHaveURL(/wp-admin/, { timeout: 15000 });

    await page.context().storageState({ path: authFile });
});
