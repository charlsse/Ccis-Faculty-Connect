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
        if ([390, 1440].includes(width)) await page.screenshot({ path: path.join(directory, `${label}-${theme}-${width}.png`), fullPage: !(await page.locator('dialog[open]').count()) });
    }
}
async function goto(page, url) { await page.goto(url); await page.waitForLoadState('networkidle'); }
(async()=>{
 browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const officer=await login('treasurer'); const admin=await login('admin');
 await goto(officer,base+'/dashboard/water/create');await officer.locator('[name=title]').fill('QA final review collection');await officer.locator('[name=amount]').fill('100');await officer.locator('[name=due_date]').fill('2026-09-30');await officer.locator('[name="members[]"][value="'+accounts.treasurer.id+'"]').check();await officer.getByRole('button',{name:'Save draft',exact:true}).click();await officer.waitForLoadState('networkidle');await officer.locator('form[action$="/status"] [name=confirmed]').check();await officer.getByRole('button',{name:'Apply',exact:true}).click();await confirm(officer);
 const receipt=await browser.newPage({viewport:{width:420,height:560}});
 await receipt.setContent('<body style="padding:30px;background:#fff;color:#183c2d;font:18px sans-serif"><h1>SYNTHETIC RECEIPT</h1><p>QA only — no real payment</p><hr><p>Recipient: Synthetic CCIS Club</p><p>Amount: PHP 100.00</p><p>Reference: QA-OFFICER-003</p><p>Test transaction for payment review.</p></body>');
 await receipt.screenshot({path:path.join(directory,'receipt-proof.png')});await receipt.close();
 await goto(officer,base+'/dashboard/water');await layouts(officer,'treasurer-overview');
 await officer.getByRole('link',{name:'Pay Now',exact:true}).first().click();await officer.waitForLoadState('networkidle');
 const checkoutUrl=officer.url();
 await officer.getByRole('link',{name:'Continue to Payment',exact:true}).click();await officer.getByRole('link',{name:'Show payment QR',exact:true}).click();
 await layouts(officer,'checkout-qr');
 await officer.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'));Object.defineProperty(document,'hidden',{configurable:true,value:false});document.dispatchEvent(new Event('visibilitychange'));delete document.hidden;});
 assert.ok(await officer.locator('[data-return-dialog]').isVisible());
 await layouts(officer,'return-confirmation');
 await officer.keyboard.press('Escape');assert.ok(await officer.locator('[data-return-dialog]').isHidden());
 await officer.getByRole('button',{name:"I've Completed Payment",exact:true}).focus();await officer.keyboard.press('Enter');
 await officer.getByRole('button',{name:'Yes, I Paid',exact:true}).focus();await officer.keyboard.press('Enter');
 assert.equal(await officer.evaluate(()=>document.activeElement.id),'payment-proof');
 await officer.locator('[name=reference_number]').fill('QA-OFFICER-003');await officer.locator('[name=payment_time]').fill('00:01');
 await officer.locator('[name=proof]').setInputFiles(path.join(directory,'receipt-proof.png'));await layouts(officer,'proof-upload');
 await officer.locator('[name=confirmed]').check();await officer.getByRole('button',{name:'Submit for Verification',exact:true}).click();await confirm(officer);
 await goto(admin,base+'/dashboard/water/queue');await admin.getByRole('link',{name:'Review',exact:true}).click();await admin.waitForLoadState('networkidle');await layouts(admin,'payment-review');
 await admin.locator('[name=confirmed]').check();await admin.getByRole('button',{name:'Save review',exact:true}).click();await confirm(admin);
 await goto(officer,checkoutUrl);assert.ok(await officer.getByRole('link',{name:'View verified receipt',exact:true}).isVisible());
 await goto(admin,base+'/dashboard/water/audit');await layouts(admin,'audit');
 await admin.locator('[name=action]').fill('water.approve');await admin.getByRole('button',{name:'Filter activity',exact:true}).click();await admin.waitForLoadState('networkidle');
 assert.ok(await admin.getByRole('heading',{name:'water.approve',exact:true}).count()>0);assert.equal(await admin.getByRole('heading',{name:'water.submitted',exact:true}).count(),0);
 await goto(admin,base+'/dashboard/water/1');await layouts(admin,'collection-overview');
 const faculty=await login('faculty');
 const state=await faculty.context().storageState();const noJs=await browser.newContext({javaScriptEnabled:false,storageState:state,viewport:{width:390,height:900}});
 const plain=await noJs.newPage();await goto(plain,base+'/dashboard/water/members/2/pay');
 // Use a new unpaid collection assignment for a no-JS checkout inspection through the officer's own draft/publication workflow.
 await goto(officer,base+'/dashboard/water/create');await officer.locator('[name=title]').fill('QA fallback collection');await officer.locator('[name=amount]').fill('100');await officer.locator('[name=due_date]').fill('2026-09-30');await officer.locator(`[name="members[]"][value="${accounts.faculty.id}"]`).check();await officer.getByRole('button',{name:'Save draft',exact:true}).click();await officer.waitForLoadState('networkidle');
 await officer.locator('form[action$="/status"] [name=confirmed]').check();await officer.getByRole('button',{name:'Apply',exact:true}).click();await confirm(officer);
 await goto(faculty,base+'/dashboard/water');await faculty.getByRole('link',{name:'Pay Now',exact:true}).first().click();const fallback=faculty.url();await goto(plain,fallback);
 assert.ok(await plain.locator('[name=proof]').isVisible());assert.ok(await plain.getByRole('link',{name:'Show payment QR',exact:true}).isVisible());
 await plain.screenshot({path:path.join(directory,'no-js-checkout-390.png'),fullPage:true});await noJs.close();
 await faculty.setViewportSize({width:390,height:900});await goto(faculty,base+'/dashboard/water');await faculty.locator('.payment-category-menu summary').focus();await faculty.keyboard.press('Enter');assert.ok(await faculty.locator('.payment-category-menu a').last().isVisible());
 await faculty.getByRole('button',{name:'Open menu',exact:true}).click();assert.ok(await faculty.locator('.sidebar-nav').getByRole('link',{name:/Payments & Collections/}).isVisible());await faculty.getByRole('button',{name:'Close menu',exact:true}).click();
 results.workflows.push('Simulated document visibility return prompt, Escape, keyboard confirmation, correct focus restoration, Treasurer proof submission and Admin approval, readable proof preview, audit filters, no-JS fallback, mobile category and sidebar keyboard access');
 fs.writeFileSync(path.join(directory,'final-browser-results.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({workflows:results.workflows,layoutChecks:results.layouts.length,errors:results.errors},null,2));assert.equal(results.errors.length,0);
})().catch(e=>{console.error(e.stack);fs.writeFileSync(path.join(directory,'final-browser-results.json'),JSON.stringify(results,null,2));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
