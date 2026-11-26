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
const sizes=[[320,568],[360,640],[375,667],[390,844],[393,852],[412,915],[430,932],[667,375],[844,390],[768,1024],[900,900],[980,800],[1024,768],[1280,800],[1440,900],[1920,1080]];
async function shot(page,label,w,h,theme){
 const metrics=await page.evaluate(()=>{const d=document.querySelector('[data-qr-dialog][open]');const b=d?.querySelector('.payment-sheet-body');const r=d?.getBoundingClientRect();return {doc:document.documentElement.scrollWidth,width:innerWidth,modal:d?d.scrollWidth-d.clientWidth:0,body:b?b.scrollWidth-b.clientWidth:0,top:r?.top,bottom:r?.bottom,height:innerHeight};});
 assert.ok(metrics.doc<=w&&metrics.modal<=1&&metrics.body<=1,JSON.stringify({label,metrics}));
 if(metrics.top!=null)assert.ok(metrics.top>=0&&metrics.bottom<=h+1,JSON.stringify({label,metrics}));
 results.layouts.push({label,w,h,theme,metrics});
 if([320,390,667,1440].includes(w)) await page.screenshot({path:path.join(directory,'mobile-'+label+'-'+theme+'-'+w+'.png')});
}
(async()=>{
 browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const states=JSON.parse(fs.readFileSync(path.join(directory,'states.json')));
 const page=await login('faculty');
 for(const theme of ['light','dark'])for(const [w,h] of sizes){
  await page.setViewportSize({width:w,height:h});await goto(page,base+'/dashboard/water');await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);
  await shot(page,'header',w,h,theme);
  if(w<=980){
   const box=await page.locator('[data-sidebar-open]').boundingBox();assert.equal(box.width,44);assert.equal(box.height,44);
   await page.evaluate(()=>scrollTo(0,100));const scrollBefore=await page.evaluate(()=>scrollY);
   await page.locator('[data-sidebar-open]').evaluate(button=>button.click());assert.equal(await page.evaluate(()=>document.body.style.position),'fixed');await shot(page,'sidebar',w,h,theme);
   await page.keyboard.press('Escape');await page.waitForFunction(()=>document.body.style.position!=='fixed');assert.notEqual(await page.evaluate(()=>document.body.style.position),'fixed');assert.equal(await page.evaluate(()=>scrollY),scrollBefore);
  }
  await goto(page,states.water.checkout);await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);
  const modal=page.locator('[data-qr-dialog]');await modal.waitFor({state:'visible'});
  await page.evaluate(()=>{document.querySelector('.payment-sheet-collection').textContent='CCIS Faculty Special Activity and Water Contribution \u2013 September 2026';document.querySelector('.payment-sheet-body .checkout-amount').textContent='\u20b112,500.00';});
  for(const value of [100,500,1000,12500]){await page.locator('.payment-sheet-body .checkout-amount').evaluate((e,v)=>e.textContent=String.fromCharCode(0x20b1)+v.toLocaleString('en-US',{minimumFractionDigits:2}),value);assert.ok(await modal.evaluate(e=>e.scrollWidth<=e.clientWidth+1));}
  const close=await modal.locator('[data-qr-close]').boundingBox();assert.equal(close.width,44);assert.equal(close.height,44);
  assert.equal(await page.evaluate(()=>document.body.style.position),'fixed');
  await shot(page,'qr-top',w,h,theme);
  await modal.locator('.payment-sheet-body').evaluate(e=>e.scrollTop=e.scrollHeight/2);await shot(page,'qr-middle',w,h,theme);
  await modal.locator('.payment-sheet-body').evaluate(e=>e.scrollTop=e.scrollHeight);await shot(page,'qr-bottom',w,h,theme);
  assert.ok(await modal.locator('[data-qr-close]').isVisible());
  await modal.getByRole('button',{name:'I Have Paid',exact:true}).click();assert.ok(await modal.isVisible());
  await page.locator('[name=reference_number]').fill('123456789012345678901234');await page.locator('[name=reference_number]').focus();
  await page.locator('[name=proof]').setInputFiles({name:'gcash-payment-september-water-contribution-screenshot.jpg',mimeType:'image/png',buffer:fs.readFileSync(path.join(directory,'proof.png'))});
  await shot(page,'proof',w,h,theme);
  await page.locator('[data-proof-preview]').scrollIntoViewIfNeeded();await shot(page,'proof-preview',w,h,theme);
  if(w===390){await page.setViewportSize({width:390,height:400});await page.locator('[name=reference_number]').focus();await page.locator('[name=reference_number]').scrollIntoViewIfNeeded();await shot(page,'keyboard-height',390,400,theme);await page.setViewportSize({width:w,height:h});}
  await modal.getByRole('button',{name:'Back to QR',exact:true}).click();
  await modal.getByRole('button',{name:'I Have Paid',exact:true}).click();assert.equal(await page.locator('[name=reference_number]').inputValue(),'123456789012345678901234');
  await page.keyboard.press('Escape');await page.waitForFunction(()=>document.body.style.position!=='fixed');assert.notEqual(await page.evaluate(()=>document.body.style.position),'fixed');
  await page.getByRole('link',{name:'Pay Now',exact:true}).click();assert.ok(await modal.isVisible());for(let n=0;n<10;n++){await page.keyboard.press('Tab');assert.ok(await modal.evaluate(e=>e.contains(document.activeElement)),JSON.stringify(await page.evaluate(()=>({active:document.activeElement.outerHTML.slice(0,300),dialogs:[...document.querySelectorAll('dialog[open]')].map(e=>e.outerHTML.slice(0,100))}))));}
  await page.keyboard.press('Escape');await page.waitForFunction(()=>document.body.style.position!=='fixed');
 }
 await page.setViewportSize({width:390,height:844});await goto(page,states.water.checkout);
 const download=page.waitForEvent('download');await page.getByRole('link',{name:'Save QR',exact:true}).click();await(await download).saveAs(path.join(directory,'mobile-downloaded-qr.png'));
 await page.getByRole('button',{name:'I Have Paid',exact:true}).click();await page.locator('[name=reference_number]').fill('INVALID@REF');await page.locator('[name=payment_date]').fill('2026-09-27');await page.locator('[name=payment_time]').fill('10:30');await page.locator('[name=proof]').setInputFiles(path.join(directory,'proof.png'));await page.locator('[name=confirmed]').check();await page.getByRole('button',{name:'Submit Payment',exact:true}).click();await page.getByRole('button',{name:'Confirm and submit',exact:true}).click();await page.waitForLoadState('networkidle');
 assert.ok(await page.locator('[data-qr-dialog] .water-error').isVisible());await shot(page,'validation',390,844,'light');
 await page.locator('[name=reference_number]').fill('MOBILE-UI-'+Date.now());await page.locator('[name=proof]').setInputFiles(path.join(directory,'proof.png'));await page.locator('[name=confirmed]').check();await page.getByRole('button',{name:'Submit Payment',exact:true}).click();await confirm(page);
 assert.ok(await page.locator('[data-qr-dialog]').getByRole('heading',{name:'Pending Treasurer Verification',exact:true}).isVisible());
 for(const theme of ['light','dark'])for(const [w,h] of sizes){await page.setViewportSize({width:w,height:h});await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);await shot(page,'pending',w,h,theme);}
 await page.keyboard.press('Escape');await page.waitForFunction(()=>document.body.style.position!=='fixed');assert.notEqual(await page.evaluate(()=>document.body.style.position),'fixed');
 results.workflows.push('Header/sidebar, one modal scroll region, fixed header, Close/Escape/reopen, preserved form on Back, QR download, long data, validation and pending dialog');
 fs.writeFileSync(path.join(directory,'mobile-results.json'),JSON.stringify(results,null,2));console.log(JSON.stringify({checks:results.layouts.length,errors:results.errors,workflows:results.workflows}));assert.equal(results.errors.length,0);
})().catch(e=>{console.error(e.stack);fs.writeFileSync(path.join(directory,'mobile-results.json'),JSON.stringify(results,null,2));process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
