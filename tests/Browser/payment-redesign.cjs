// Run after tools/water-browser-fixture.php and the loopback-only QA server.
const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const directory = path.resolve(__dirname, '../../storage/app/private/payment-redesign-qa');
const accounts = JSON.parse(fs.readFileSync(path.join(directory, 'accounts.json')));
const base = 'http://127.0.0.1:8094';
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
    for (const theme of ['light', 'dark']) for (const width of [320,360,375,390,393,412,430,480,768,1024,1280,1440,1920]) {
        await page.evaluate(theme => { document.documentElement.dataset.theme = theme; }, theme);
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(30);
        const sizing = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth,
            controls: [...document.querySelectorAll('.water-module input:not([type=checkbox]),.water-module select,.water-module textarea')].filter(e => e.getClientRects().length).every(e => parseFloat(getComputedStyle(e).fontSize) >= 16) }));
        assert.ok(sizing.scroll <= width, `${label} overflows at ${width}: ${sizing.scroll}`);
        if (!sizing.controls) {
            const controls = await page.locator('.water-module input:not([type=checkbox]),.water-module select,.water-module textarea').evaluateAll(elements => elements.filter(e => e.getClientRects().length).map(e => ({ name: e.name, size: getComputedStyle(e).fontSize })));
            throw new Error(`${label} small input text at ${width}: ${JSON.stringify(controls)}`);
        }
        results.layouts.push({ page: label, theme, width, horizontalOverflow: false, inputsAtLeast16px: true });
        if ([390, 1440].includes(width)) await page.screenshot({ path: path.join(directory, `${label}-${theme}-${width}.png`), fullPage: true });
    }
}
async function goto(page, url) { await page.goto(url); await page.waitForLoadState('networkidle'); }
async function proof(page, reference) {
    await page.locator('[name=reference_number]').fill(reference);
    await page.locator('[name=payment_time]').fill('00:01');
    await page.locator('[name=proof]').setInputFiles(path.join(directory,'proof.png'));
    assert.ok(await page.getByAltText('Selected receipt preview').isVisible());
    await page.locator('[name=confirmed]').check();
    await page.getByRole('button',{name:'Submit for Verification',exact:true}).click();
    await confirm(page);
    assert.ok(await page.getByRole('heading',{name:'Payment submitted',exact:true}).isVisible());
}
(async()=>{
 browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const officer=await login('treasurer');
 const imagePage=await browser.newPage({viewport:{width:300,height:300}});
 await imagePage.setContent('<body style="margin:0;background:white;display:grid;place-items:center;height:300px;font:24px sans-serif;border:12px solid #24613a;box-sizing:border-box;text-align:center">SYNTHETIC<br>QR TEST IMAGE<br>NOT FOR PAYMENT</body>');
 await imagePage.screenshot({path:path.join(directory,'qr.png')}); await imagePage.close();
 await goto(officer,base+'/dashboard/water/accounts');
 await officer.locator('[name=label]').fill('QA receiving account');
 await officer.locator('[name=account_name]').fill('Synthetic CCIS Club');
 await officer.locator('[name=masked_account_number]').fill('09** *** 1234');
 await officer.locator('[name=qr]').setInputFiles(path.join(directory,'qr.png'));
 await officer.locator('[name=instructions]').fill('Synthetic browser test only. No real transactions.');
 await officer.locator('[name=confirmed]').check();
 await officer.getByRole('button',{name:'Save as active default'}).click(); await confirm(officer);
 await layouts(officer,'qr-accounts');
 await goto(officer,base+'/dashboard/water/create');
 await officer.locator('[name=title]').fill('QA September Water Contribution');
 await officer.locator('[name=description]').fill('Synthetic contribution for checkout verification.');
 await officer.locator('[name=period]').fill('2026-09');
 await officer.locator('[name=amount]').fill('100.00');
 await officer.locator('[name=due_date]').fill('2026-09-30');
 for(const role of ['faculty','treasurer','cash','waived']) await officer.locator(`[name="members[]"][value="${accounts[role].id}"]`).check();
 await layouts(officer,'collection-create');
 await officer.getByRole('button',{name:'Save draft',exact:true}).click();
 await officer.waitForURL(/\/water\/\d+$/); await officer.waitForLoadState('networkidle');
 const collectionUrl=officer.url();
 await officer.locator('form[action$="/status"] [name=confirmed]').check();
 await officer.getByRole('button',{name:'Apply',exact:true}).click(); await confirm(officer);
 await layouts(officer,'collection-overview');
 await goto(officer,collectionUrl+'?section=members'); await layouts(officer,'collection-members');
 await goto(officer,base+'/dashboard/water'); await layouts(officer,'treasurer-overview');
 await officer.getByRole('link',{name:'Pay Now',exact:true}).click();
 assert.ok(await officer.getByRole('heading',{name:'My water payment',exact:true}).isVisible());
 results.workflows.push('QR setup, create, publish, assigned Treasurer personal checkout');
 const faculty=await login('faculty');
 await goto(faculty,base+'/dashboard/water'); await layouts(faculty,'faculty-overview');
 await faculty.getByRole('link',{name:'Pay Now',exact:true}).click();
 await faculty.waitForLoadState('networkidle'); const checkoutUrl=faculty.url();
 await layouts(faculty,'checkout-summary');
 await faculty.getByRole('link',{name:'Continue to Payment',exact:true}).click(); await layouts(faculty,'payment-method');
 await faculty.getByRole('link',{name:'Show payment QR',exact:true}).click(); await layouts(faculty,'checkout-qr');
 await faculty.getByRole('button',{name:"I've Completed Payment",exact:true}).click(); await layouts(faculty,'return-confirmation');
 await faculty.getByRole('button',{name:'Not Yet / Payment Failed',exact:true}).click();
 assert.ok(await faculty.getByRole('heading',{name:'No problem. You can try again.',exact:true}).isVisible());
 await faculty.getByRole('link',{name:'Show QR Again',exact:true}).click();
 await faculty.getByRole('button',{name:"I've Completed Payment",exact:true}).click();
 await faculty.getByRole('button',{name:'Yes, I Paid',exact:true}).click();
 await faculty.locator('[name=proof]').setInputFiles(path.join(directory,'qr.png')); await layouts(faculty,'proof-upload');
 await faculty.getByRole('button',{name:'Remove receipt',exact:true}).click();
 assert.equal(await faculty.locator('[name=proof]').evaluate(e=>e.files.length),0);
 await proof(faculty,'QA-REDESIGN-001'); await layouts(faculty,'pending-payment');
 await goto(officer,base+'/dashboard/water/queue'); await layouts(officer,'verification-queue');
 await officer.getByRole('link',{name:'Review',exact:true}).click(); await officer.waitForLoadState('networkidle');
 const submissionUrl=officer.url(); await layouts(officer,'payment-review');
 await officer.locator('[name=decision]').selectOption('correct');
 await officer.locator('[name=rejection_reason]').fill('Receipt image is unclear. Please upload a readable copy.');
 await officer.locator('[name=confirmed]').check();
 await officer.getByRole('button',{name:'Save review',exact:true}).click(); await confirm(officer);
 await goto(faculty,checkoutUrl); assert.ok(await faculty.getByText('Payment needs attention:',{exact:false}).isVisible());
 await faculty.getByRole('link',{name:'Continue to Payment',exact:true}).click();
 await faculty.getByRole('link',{name:'Manual payment instructions / Having trouble?',exact:true}).click();
 await faculty.getByRole('link',{name:'Already paid to the assigned account? Submit proof',exact:true}).click();
 await proof(faculty,'QA-REDESIGN-001');
 await goto(officer,base+'/dashboard/water/queue'); await officer.getByRole('link',{name:'Review',exact:true}).click();
 await officer.waitForLoadState('networkidle'); await officer.locator('[name=confirmed]').check();
 await officer.getByRole('button',{name:'Save review',exact:true}).click(); await confirm(officer);
 await goto(faculty,checkoutUrl); await layouts(faculty,'paid-payment');
 await faculty.getByRole('link',{name:'View verified receipt',exact:true}).click(); await layouts(faculty,'receipt');
 results.workflows.push('Pay Now, QR, return, retry without mutation, image preview/removal, proof submission, correction, resubmission, approval, verified receipt');
 const cash=await login('cash'); assert.equal((await cash.goto(submissionUrl+'/proof')).status(),403);
 assert.equal((await cash.goto(checkoutUrl)).status(),403);
 await goto(officer,collectionUrl+'?section=members');
 await officer.locator('article.water-card').filter({has:officer.getByRole('heading',{name:'QA Cash Synthetic',exact:true})}).getByRole('link',{name:'Record cash payment',exact:true}).click();
 await officer.locator('[name=payment_time]').fill('00:01');await officer.locator('[name=confirmed]').check();
 await officer.getByRole('button',{name:'Review cash payment',exact:true}).click();await confirm(officer);
 await goto(officer,collectionUrl+'?section=members');
 const waiver=officer.locator('article.water-card').filter({has:officer.getByRole('heading',{name:'QA Waived Synthetic',exact:true})});
 await waiver.getByText('Authorize a waiver',{exact:true}).click(); await waiver.locator('[name=reason]').fill('Synthetic authorized waiver');await waiver.locator('[name=confirmed]').check();await waiver.getByRole('button',{name:'Record waiver',exact:true}).click();await confirm(officer);
 await goto(officer,base+'/dashboard/water/reports');await layouts(officer,'reports');
 await officer.getByRole('link',{name:'Report & CSV export',exact:true}).click();await layouts(officer,'collection-report');
 const csv=officer.waitForEvent('download');await officer.getByRole('link',{name:'Download CSV report',exact:true}).click();await(await csv).saveAs(path.join(directory,'collection.csv'));
 await goto(officer,base+'/dashboard/collections');await layouts(officer,'payment-records');
 assert.equal(await officer.locator('[name=reference_no]').count(),0);
 await officer.getByRole('link',{name:'Add Manual Record',exact:true}).click();await layouts(officer,'manual-record');
 const admin=await login('admin');await goto(admin,base+'/dashboard/water/audit');await layouts(admin,'audit');
 results.workflows.push('private proofs and checkout deny other faculty, cash fallback, waiver, reports and CSV, manual records tucked away, admin-only audit');
 fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(results,null,2));
 console.log(JSON.stringify({workflows:results.workflows,layoutChecks:results.layouts.length,errors:results.errors},null,2));
 assert.equal(results.errors.length,0);
})().catch(e=>{console.error(e.stack);fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(results,null,2));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
