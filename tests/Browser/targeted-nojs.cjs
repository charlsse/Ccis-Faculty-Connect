const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const dir = 'storage/app/private/targeted-qa';
const accounts = JSON.parse(fs.readFileSync(dir + '/auth-accounts.json'));
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const page = await context.newPage();
        const base = 'http://127.0.0.1:8092';
        await page.goto(base + '/reset-password/' + accounts.reset.token + '?email=' + encodeURIComponent(accounts.reset.email));
        await page.locator('[name=password]').fill('NoScript!Password123');
        await page.locator('[name=password_confirmation]').fill('NoScript!Password123');
        await page.getByRole('button', { name: 'Reset Password', exact: true }).click();
        await page.waitForURL('**/login');
        await page.locator('[name=login]').fill(accounts.temporary.email);
        await page.locator('[name=password]').fill(accounts.temporary.password);
        await page.getByRole('button', { name: 'Login', exact: true }).click();
        await page.locator('[name=password_confirmation]').waitFor();
        await page.locator('[name=password]').fill('NoScript!Password123');
        await page.locator('[name=password_confirmation]').fill('NoScript!Password123');
        await page.getByRole('button', { name: 'Replace temporary password', exact: true }).click();
        await page.waitForURL('**/verify-email');
        assert.ok(page.url().endsWith('/verify-email'));
        fs.writeFileSync(dir + '/nojs-results.json', JSON.stringify({ passwordReset: 'passed', temporaryReplacement: 'passed', javaScriptEnabled: false }));
        console.log('Both password workflows submitted successfully with JavaScript disabled at 390px.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
