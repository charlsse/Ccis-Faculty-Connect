// Run after tools/water-browser-fixture.php and the loopback-only QA server.
const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const directory = path.resolve(__dirname, '../../storage/app/private/water-qa');
const accounts = JSON.parse(fs.readFileSync(path.join(directory, 'accounts.json')));
const base = 'http://127.0.0.1:8091';
const results = { workflows: [], layouts: [], errors: [] };
let browser;
async function confirm(page) {
    await page.getByRole('button', { name: 'Confirm and submit' }).click();
    await page.waitForLoadState('networkidle');
    const errors = page.locator('.water-error');
    if (await errors.count()) throw new Error(await errors.textContent());
}
async function login(role) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.on('pageerror', error => results.errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && !message.text().includes('net::ERR_') && !message.text().includes('403 (Forbidden)')) results.errors.push(message.text()); });
    await page.goto(base + '/login');
    await page.locator('[name=login]').fill(accounts[role].email);
    await page.locator('[name=password]').fill(accounts[role].password);
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await page.locator('[name=code]').fill(accounts[role].recovery);
    await page.getByRole('button', { name: 'Verify & Sign In' }).click();
    await page.waitForURL('**/dashboard');
    results.workflows.push(role + ' password + 2FA login');
    return page;
}
async function layouts(page, label) {
    await page.waitForLoadState('networkidle');
    for (const width of [360, 390, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(100);
        const sizing = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth,
            controls: [...document.querySelectorAll('.water-module input:not([type=checkbox]),.water-module select,.water-module textarea')].filter(e => e.getClientRects().length).every(e => parseFloat(getComputedStyle(e).fontSize) >= 16) }));
        assert.ok(sizing.scroll <= width, `${label} overflows at ${width}: ${sizing.scroll}`);
        if (!sizing.controls) {
            const controls = await page.locator('.water-module input:not([type=checkbox]),.water-module select,.water-module textarea').evaluateAll(elements => elements.filter(e => e.getClientRects().length).map(e => ({ name: e.name, size: getComputedStyle(e).fontSize })));
            throw new Error(`${label} small input text at ${width}: ${JSON.stringify(controls)}`);
        }
        results.layouts.push({ page: label, width, horizontalOverflow: false, inputsAtLeast16px: true });
        if ([360, 768, 1440].includes(width)) await page.screenshot({ path: path.join(directory, `${label}-${width}.png`), fullPage: true });
    }
}
async function paymentForm(page, reference) {
    await page.locator('[name=reference_number]').fill(reference);
    await page.locator('[name=payment_time]').fill('00:01');
    await page.locator('[name=proof]').setInputFiles(path.join(directory, 'proof.png'));
    await page.locator('[name=confirmed]').check();
    await page.waitForLoadState('networkidle'); await page.getByRole('button', { name: 'Review submission', exact: true }).click();
    await page.locator('dialog').waitFor();
    await layouts(page, 'confirmation');
    await confirm(page);
}
(async () => {
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const officer = await login('treasurer');
    // Create a clearly synthetic test image; no real account or payment data is used.
    const imagePage = await browser.newPage({ viewport: { width: 300, height: 300 } });
    await imagePage.setContent('<body style="margin:0;background:white;display:grid;place-items:center;height:300px;font:24px sans-serif;border:12px solid #24613a;box-sizing:border-box;text-align:center">SYNTHETIC<br>QR TEST IMAGE<br>NOT FOR PAYMENT</body>');
    await imagePage.screenshot({ path: path.join(directory, 'qr.png') });
    await imagePage.close();
    await officer.goto(base + '/dashboard/water/accounts');
    await officer.locator('[name=label]').fill('QA official destination');
    await officer.locator('[name=account_name]').fill('Synthetic CCIS Club');
    await officer.locator('[name=masked_account_number]').fill('09** *** 1234');
    await officer.locator('[name=qr]').setInputFiles(path.join(directory, 'qr.png'));
    await officer.locator('[name=instructions]').fill('Synthetic browser test only. No real transactions.');
    await officer.locator('[name=confirmed]').check();
    await officer.waitForLoadState('networkidle'); await officer.getByRole('button', { name: 'Save as active default' }).click(); await confirm(officer);
    await layouts(officer, 'accounts');
    await officer.goto(base + '/dashboard/water/create');
    await officer.locator('[name=title]').fill('QA September Water Collection');
    await officer.locator('[name=description]').fill('Synthetic collection for verification of the complete workflow.');
    await officer.locator('[name=period]').fill('2026-09');
    await officer.locator('[name=amount]').fill('100.00');
    await officer.locator('[name=due_date]').fill('2026-09-30');
    for (const role of ['faculty', 'cash', 'waived']) await officer.locator(`[name="members[]"][value="${accounts[role].id}"]`).check();
    await layouts(officer, 'create');
    await officer.waitForLoadState('networkidle'); await officer.getByRole('button', { name: 'Save draft', exact: true }).click();
    await officer.waitForURL(/\/dashboard\/water\/\d+$/);
    const collectionUrl = officer.url();
    const faculty = await login('faculty');
    await faculty.goto(base + '/dashboard/water');
    assert.equal(await faculty.getByRole('link', { name: 'QA September Water Collection', exact: true }).count(), 0);
    await officer.locator('[name=status]').selectOption('published');
    await officer.locator('form[action$="/status"] [name=confirmed]').check();
    await officer.waitForLoadState('networkidle'); await officer.getByRole('button', { name: 'Apply', exact: true }).click(); await confirm(officer);
    results.workflows.push('QR settings, selected assignments, hidden draft, publication');
    await faculty.goto(collectionUrl); await layouts(faculty, 'faculty-payment');
    await faculty.setViewportSize({ width: 360, height: 1000 });
    await faculty.getByRole('button', { name: 'Open menu', exact: true }).click();
    assert.ok(await faculty.getByRole('link', { name: 'Water Collections', exact: true }).isVisible());
    await faculty.getByRole('button', { name: 'Close menu', exact: true }).click();
    await paymentForm(faculty, 'QA-REF-001');
    assert.ok(await faculty.getByText('Awaiting manual verification.', { exact: false }).isVisible());
    await officer.goto(base + '/dashboard/water/queue'); await layouts(officer, 'queue');
    await officer.getByRole('link', { name: 'Review details & proof' }).click();
    const submissionUrl = officer.url();
    await layouts(officer, 'review');
    await officer.locator('[name=decision]').selectOption('correct');
    await officer.locator('[name=rejection_reason]').fill('Please provide a readable proof.');
    await officer.locator('[name=confirmed]').check();
    await officer.waitForLoadState('networkidle'); await officer.getByRole('button', { name: 'Save review' }).click(); await confirm(officer);
    await faculty.goto(collectionUrl);
    assert.ok(await faculty.getByText('Rejected: Please provide a readable proof.', { exact: false }).isVisible());
    await paymentForm(faculty, 'QA-REF-001');
    await officer.goto(base + '/dashboard/water/queue');
    await officer.getByRole('link', { name: 'Review details & proof' }).click();
    await officer.locator('[name=confirmed]').check();
    await officer.waitForLoadState('networkidle'); await officer.getByRole('button', { name: 'Save review' }).click(); await confirm(officer);
    await officer.getByRole('link', { name: 'Open verified receipt' }).click();
    await layouts(officer, 'receipt');
    const downloadPromise = officer.waitForEvent('download');
    await officer.getByRole('link', { name: 'Download PDF', exact: true }).click();
    await (await downloadPromise).saveAs(path.join(directory, 'verified-receipt.pdf'));
    results.workflows.push('faculty submission, officer correction, same-reference resubmission, approval, PDF receipt');
    const cash = await login('cash');
    const forbidden = await cash.goto(submissionUrl + '/proof');
    assert.equal(forbidden.status(), 403);
    results.workflows.push('another member proof authorization denied');
    await officer.goto(collectionUrl);
    const cashCard = officer.locator('article.water-card').filter({ has: officer.getByRole('heading', { name: 'QA Cash Synthetic', exact: true }) });
    await cashCard.getByRole('link', { name: 'Record cash payment' }).click();
    await officer.locator('[name=payment_time]').fill('00:01');
    await officer.locator('[name=confirmed]').check();
    await layouts(officer, 'cash');
    await officer.waitForLoadState('networkidle'); await officer.getByRole('button', { name: 'Review cash payment' }).click(); await confirm(officer);
    const waivedCard = officer.locator('article.water-card').filter({ has: officer.getByRole('heading', { name: 'QA Waived Synthetic', exact: true }) });
    await waivedCard.getByText('Authorize a waiver', { exact: true }).click();
    await waivedCard.locator('[name=reason]').fill('Synthetic authorized waiver');
    await waivedCard.locator('[name=confirmed]').check();
    await officer.waitForLoadState('networkidle'); await waivedCard.getByRole('button', { name: 'Record waiver' }).click(); await confirm(officer);
    await officer.getByRole('link', { name: 'Report & CSV export' }).click(); await layouts(officer, 'report');
    const csvPromise = officer.waitForEvent('download');
    await officer.getByRole('link', { name: 'Download CSV report' }).click();
    await (await csvPromise).saveAs(path.join(directory, 'collection.csv'));
    await officer.goto(collectionUrl);
    for (const status of ['closed', 'archived']) {
        await officer.locator('[name=status]').selectOption(status);
        await officer.locator('form[action$="/status"] [name=confirmed]').check();
        await officer.waitForLoadState('networkidle'); await officer.getByRole('button', { name: 'Apply', exact: true }).click(); await confirm(officer);
    }
    await faculty.goto(base + '/dashboard/water'); await layouts(faculty, 'history');
    assert.ok(await faculty.locator('.water-badge').filter({ hasText: /^Archived$/ }).isVisible());
    const admin = await login('admin');
    await admin.goto(base + '/dashboard/water/audit'); await layouts(admin, 'audit');
    results.workflows.push('cash, waiver, correct report totals, CSV download, close, archive, faculty history, administrator audit');
    fs.writeFileSync(path.join(directory, 'browser-results.json'), JSON.stringify(results, null, 2));
    console.log(JSON.stringify({ workflows: results.workflows, layoutChecks: results.layouts.length, consoleErrors: results.errors }, null, 2));
    assert.equal(results.errors.length, 0, 'Unexpected browser console errors');
})().catch(error => { console.error(error.message); fs.writeFileSync(path.join(directory, 'browser-results.json'), JSON.stringify(results, null, 2)); process.exitCode = 1; }).finally(async () => { if (browser) await browser.close(); });
