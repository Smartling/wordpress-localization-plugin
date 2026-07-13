const { test, expect } = require('@playwright/test');
const path = require('path');

const authFile = path.join(__dirname, '.auth/admin.json');

test('login as admin', async ({ page }) => {
    const user = process.env.WP_ADMIN_USER || 'wp';
    const pass = process.env.WP_ADMIN_PASSWORD || 'wp';

    await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await page.click('#wp-submit');

    await expect(page).toHaveURL(/wp-admin/, { timeout: 15000 });

    await page.context().storageState({ path: authFile });
});
