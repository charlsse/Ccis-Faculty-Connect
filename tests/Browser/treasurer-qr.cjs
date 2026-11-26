// Run after tools/water-browser-fixture.php and the loopback-only QA server.
const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const directory = path.resolve(__dirname, '../../storage/app/private/treasurer-qr-qa');
const accounts = JSON.parse(fs.readFileSync(path.join(directory, 'accounts.json')));
const base = 'http://127.0.0.1:8097';
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
 const states=JSON.parse(fs.readFileSync(path.join(directory,'states.json')));
 const faculty=await login('faculty');const officer=await login('treasurer');const admin=await login('admin');
 for(const category of ['water','other']){
  await goto(faculty,base+'/dashboard/water?category='+category);
  assert.equal(await faculty.getByText('PayMongo',{exact:false}).count(),0);
  await faculty.getByRole('link',{name:'Pay Now',exact:true}).first().click();
  const qr=faculty.locator('[data-qr-dialog]');await qr.waitFor({state:'visible'});
  assert.ok(await qr.getByText('Synthetic Club Treasurer',{exact:true}).isVisible());
  assert.ok(await qr.locator('img').evaluate(img=>img.complete&&img.naturalWidth>0));
  await layouts(faculty,category+'-qr-popup');
  await faculty.keyboard.press('Escape');assert.equal(await qr.isVisible(),false);
  await faculty.getByRole('link',{name:'Pay Now',exact:true}).click();await qr.waitFor({state:'visible'});
  await qr.getByRole('button',{name:'I Have Paid',exact:true}).click();
  await faculty.locator('[name=reference_number]').fill('QA-TREASURER-'+category.toUpperCase()+'-'+Date.now());
  await faculty.locator('[name=payment_date]').fill('2026-09-27');await faculty.locator('[name=payment_time]').fill('10:30');
  await faculty.locator('[name=proof]').setInputFiles(path.join(directory,'proof.png'));
  await faculty.locator('[name=confirmed]').check();await layouts(faculty,category+'-proof');
  await faculty.getByRole('button',{name:'Submit Payment',exact:true}).click();await confirm(faculty);
  assert.ok(await faculty.getByRole('heading',{name:'Pending Treasurer Verification',exact:true}).isVisible());
  assert.equal(await faculty.locator('[data-checkout-panel=qr]').count(),0);await layouts(faculty,category+'-pending');
  const submission=await faculty.getByRole('link',{name:'View Payment',exact:true}).getAttribute('href');
  await goto(admin,submission);assert.equal(await admin.locator('[name=decision]').count(),0);assert.ok(await admin.getByRole('link',{name:'Download proof',exact:true}).isVisible());
  await goto(officer,submission);await officer.locator('[name=decision]').selectOption('approve');await officer.locator('[name=confirmed]').check();
  await officer.getByRole('button',{name:'Save review',exact:true}).click();await confirm(officer);
  const receipt=await officer.getByRole('link',{name:'Open verified receipt',exact:true}).getAttribute('href');
  await goto(faculty,receipt);await layouts(faculty,category+'-receipt');
  assert.ok(await faculty.getByText('QA Treasurer Synthetic',{exact:false}).isVisible());
  await goto(admin,states[category].collection+'/report');await layouts(admin,category+'-admin-report');
  await goto(admin,receipt);assert.ok(await admin.getByRole('link',{name:'Download PDF',exact:true}).isVisible());
  results.workflows.push(category+': Pay Now popup, Escape/reopen, proof, pending, Treasurer approval, receipt and Admin read-only report');
 }
 await goto(officer,base+'/dashboard/water/create');assert.equal(await officer.locator('[name=category] option').count(),2);
 await goto(admin,base+'/dashboard/water?section=collections');assert.equal(await admin.getByRole('link',{name:'Create collection',exact:true}).count(),0);
 await goto(faculty,base+'/dashboard/water');assert.equal(await faculty.getByRole('link',{name:'Pay Now',exact:true}).count(),0);
 fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({workflows:results.workflows,layoutChecks:results.layouts.length,errors:results.errors},null,2));assert.equal(results.errors.length,0);
})().catch(e=>{console.error(e.stack);fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(results,null,2));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
