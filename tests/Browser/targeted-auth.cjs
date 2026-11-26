const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const directory = path.resolve(__dirname, '../../storage/app/private/targeted-qa');
const accounts = JSON.parse(fs.readFileSync(path.join(directory, 'auth-accounts.json')));
const base = 'http://127.0.0.1:8092';
const results = { layouts: [], workflows: [], errors: [] };
let browser;
async function page() {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    page.on('pageerror', e => results.errors.push(e.message));
    return page;
}
async function layouts(page, label) {
    await page.waitForLoadState('networkidle');
    for (const width of [320, 360, 390, 430, 768, 1280]) {
        await page.setViewportSize({ width, height: 844 });
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const metrics = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth,
            smallInputs: [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]), select, textarea')]
                .filter(e => e.getClientRects().length && parseFloat(getComputedStyle(e).fontSize) < 16).map(e => e.name) }));
        assert.ok(metrics.scroll <= width, `${label} overflow at ${width}: ${metrics.scroll}`);
        assert.deepEqual(metrics.smallInputs, [], `${label} small inputs at ${width}`);
        results.layouts.push({ label, width, ...metrics });
        if ([390, 1280].includes(width)) await page.screenshot({ path: path.join(directory, `${label}-${width}.png`), fullPage: true });
    }
    await page.setViewportSize({ width: 390, height: 844 });
}
async function login(label, destination = 'dashboard') {
    const p = await page();
    await p.goto(base + '/login');
    await p.locator('[name=login]').fill(accounts[label].email);
    await p.locator('[name=password]').fill(accounts[label].password);
    await p.getByRole('button', { name: 'Login', exact: true }).click();
    if (destination === 'dashboard') {
        if (label === 'member') await layouts(p, 'two-factor-challenge');
        await p.locator('[name=code]').fill(accounts[label].recovery);
        await p.getByRole('button', { name: 'Verify & Sign In' }).click();
        await p.waitForURL('**/dashboard');
    }
    return p;
}
async function passwords(p, label) {
    await layouts(p, label);
    const first = p.locator('[name=password]');
    const second = p.locator('[name=password_confirmation]');
    await first.fill('Synthetic!NewPassword123');
    await second.fill('Synthetic!NewPassword123');
    const before = p.url();
    const toggles = p.locator('[data-password-toggle]');
    await toggles.nth(0).click();
    assert.equal(await first.getAttribute('type'), 'text');
    assert.equal(await second.getAttribute('type'), 'password');
    await toggles.nth(1).click();
    await toggles.nth(0).click();
    assert.equal(await first.getAttribute('type'), 'password');
    assert.equal(await second.getAttribute('type'), 'text');
    await toggles.nth(1).click();
    assert.equal(p.url(), before);
    await p.locator('[data-password-submit] [type=submit]').click();
    await p.waitForURL(url => url.toString() !== before);
    results.workflows.push(label + ': independent toggles and successful phone submission');
}
(async () => {
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const guest = await page(); await guest.goto(base + '/login'); await layouts(guest, 'login');
    const reset = accounts.reset;
    await guest.goto(base + '/reset-password/' + reset.token + '?email=' + encodeURIComponent(reset.email));
    await passwords(guest, 'reset-password');
    assert.ok(guest.url().endsWith('/login'));
    const temporary = await login('temporary', 'onboarding');
    await passwords(temporary, 'temporary-password');
    assert.ok(temporary.url().includes('verify-email'));
    const setup = await login('setup', 'setup'); await layouts(setup, 'two-factor-setup');
    assert.equal(await setup.locator('#manualSetupKey').count(), 1);
    await setup.getByRole('button', { name: 'Copy setup key' }).click();
    results.workflows.push('Authorized 2FA setup exposes manual key and copy action');
    const member = await login('member'); await layouts(member, 'member-dashboard');
    await member.getByRole('link', { name: 'View Payment Instructions', exact: true }).click();
    await layouts(member, 'member-water');
    assert.equal(await member.locator('[name=proof]').count(), 1);
    results.workflows.push('Member reaches payment instructions and proof form in one dashboard tap');
    await member.goto(base + '/dashboard/profile'); await layouts(member, 'profile');
    await member.goto(base + '/dashboard/security'); await layouts(member, 'security');
    const admin = await login('administrator'); await layouts(admin, 'admin-dashboard');
    await admin.goto(base + '/dashboard/members/create'); await layouts(admin, 'create-account');
    assert.equal(await admin.locator('select[name=status]').count(), 0);
    assert.equal(await admin.locator('select[name=role]').count(), 1);
    const secretary = await login('secretary'); await layouts(secretary, 'secretary-dashboard');
    const preview = await page(); await preview.setContent(fs.readFileSync(path.join(directory, 'invitation-preview.html'), 'utf8'));
    await layouts(preview, 'invitation-preview');
    assert.equal(results.errors.length, 0);
})().catch(error => { results.errors.push(error.message); process.exitCode = 1; }).finally(async () => {
    fs.writeFileSync(path.join(directory, 'auth-browser-results.json'), JSON.stringify(results, null, 2));
    console.log(JSON.stringify({ checks: results.layouts.length, workflows: results.workflows, errors: results.errors }, null, 2));
    if (browser) await browser.close();
});
