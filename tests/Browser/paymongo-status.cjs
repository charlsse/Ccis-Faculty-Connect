// Run after tools/water-browser-fixture.php and the loopback-only QA server.
const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const directory = path.resolve(__dirname, '../../storage/app/private/paymongo-qa');
const accounts = JSON.parse(fs.readFileSync(path.join(directory, 'accounts.json')));
const base = 'http://127.0.0.1:8096';
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
const states=JSON.parse(fs.readFileSync(path.join(directory,'states.json')));
(async()=>{
 browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const faculty=await login('faculty');
 await faculty.context().route('https://checkout.paymongo.com/**',route=>route.abort());
 await goto(faculty,states.available.checkout);
 await faculty.locator('[data-qr-close]').click();
 await faculty.getByText('Experimental online payment / Test Mode',{exact:true}).click();
 await layouts(faculty,'payment-options');
 assert.ok(await faculty.getByText('never scan a test QR',{exact:false}).isVisible());
 await faculty.getByRole('link',{name:'Pay Now',exact:true}).click();
 await faculty.getByRole('button',{name:'I Have Paid',exact:true}).click();
 assert.ok(await faculty.locator('[name=proof]').isVisible());
 results.workflows.push('PayMongo test option, simulator warning and original manual proof fallback');
 for(const state of ['pending','unknown','attention','expired','confirmed']){await goto(faculty,states[state].status);await layouts(faculty,'online-'+state);}
 await goto(faculty,states.confirmed.receipt);await layouts(faculty,'online-receipt');
 assert.ok(await faculty.getByRole('heading',{name:'TEST Faculty Club Collection Receipt',exact:true}).isVisible());
 const pdf=faculty.waitForEvent('download');await faculty.getByRole('link',{name:'Download PDF',exact:true}).click();await(await pdf).saveAs(path.join(directory,'test-receipt.pdf'));
 await goto(faculty,states.pending.checkout);assert.equal(await faculty.locator('[data-gateway-start]').count(),0);assert.equal(await faculty.locator('[name=proof]').count(),0);
 await goto(faculty,base+'/dashboard/water');await layouts(faculty,'gateway-overview');
 assert.ok(await faculty.getByRole('link',{name:'Check online payment',exact:true}).count()>=3);
 const officer=await login('treasurer');await goto(officer,base+'/dashboard/water/queue');await layouts(officer,'gateway-exceptions');
 assert.ok(await officer.getByText('Online payment exceptions',{exact:false}).isVisible());
 await goto(officer,states.confirmed.collection+'/report');await layouts(officer,'gateway-report');
 assert.ok(await officer.getByText('paymongo_test',{exact:false}).isVisible());
 // Confirm finite local-only polling. This does not simulate a provider confirmation.
 const poll=await faculty.context().newPage();let reads=0;poll.on('request',r=>{if(r.url().endsWith('/status'))reads++;});
 await poll.addInitScript(()=>{const original=window.setTimeout;window.setTimeout=(fn,delay,...args)=>original(fn,delay===5000?50:delay,...args);});
 await poll.goto(states.pending.status);
 await poll.getByText('Confirmation is taking longer than expected.',{exact:false}).waitFor({state:'visible',timeout:20000});
 assert.ok(reads<=12&&reads>0,`Expected finite polling, saw ${reads}`);
 assert.ok(await poll.getByText('Confirmation is taking longer than expected.',{exact:false}).isVisible());
 await poll.close();
 results.workflows.push('processing, unknown, exception, expiry, confirmed and receipt states; finite local-only polling; no duplicate payment form; officer exceptions; PDF and reports');
 fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({workflows:results.workflows,layoutChecks:results.layouts.length,errors:results.errors},null,2));assert.equal(results.errors.length,0);
})().catch(e=>{console.error(e.stack);fs.writeFileSync(path.join(directory,'browser-results.json'),JSON.stringify(results,null,2));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
