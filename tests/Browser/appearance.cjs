const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/app/private/appearance-qa');
const accounts = JSON.parse(fs.readFileSync(path.join(root, 'storage/app/private/targeted-qa/auth-accounts.json')));
const base = 'http://127.0.0.1:8092';
const widths = [320,360,375,390,393,412,430,480,768,1024,1280,1440,1920];
const results = { layouts: [], interactions: [], errors: [] };
let browser;
const fixture = count => execFileSync('C:/xampp/php/php.exe', ['tools/appearance-browser-fixture.php', String(count)], {cwd:root});
async function create(options = {}) {
    const context = await browser.newContext({viewport:{width:1440,height:1000}, colorScheme:'light', ...options});
    const page = await context.newPage();
    page.on('pageerror', error => results.errors.push(error.message));
    page.on('response', response => {
        if (response.request().isNavigationRequest() && response.status() >= 400) results.errors.push(`HTTP ${response.status()} ${response.url()}`);
    });
    return page;
}
async function theme(page, value) {
    if (await page.evaluate(() => document.documentElement.dataset.theme) !== value) await page.locator('[data-theme-toggle]').click();
    await page.waitForTimeout(280);
}
async function layouts(page, label) {
    for (const width of widths) {
        await page.setViewportSize({width,height:900});
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const metrics = await page.evaluate(() => {
            const visible = el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
            return { width:document.documentElement.scrollWidth,
                clipped: [...document.querySelectorAll('main input:not([type=hidden]),main select,main textarea')].filter(visible).filter(el=>el.getBoundingClientRect().right>innerWidth+1 || el.getBoundingClientRect().left < -1).map(el=>el.name),
                smallControls:[...document.querySelectorAll('[data-theme-toggle],[data-fc-controls] button')].filter(visible).filter(el=>el.offsetWidth<44 || el.offsetHeight<44).map(el=>el.getAttribute('aria-label')),
            };
        });
        assert.ok(metrics.width <= width, `${label} overflow at ${width}: ${metrics.width}`);
        assert.deepEqual(metrics.clipped,[],`${label} clipped inputs at ${width}`);
        assert.deepEqual(metrics.smallControls,[],`${label} small controls at ${width}`);
        if(label.startsWith('hero-') && width <= 760) {
            assert.ok(await page.evaluate(() => {
                const card = document.querySelector('[data-fc-visual]:not([hidden]) .fc-preview').getBoundingClientRect();
                const note = document.querySelector('.fc-security-float').getBoundingClientRect();
                return note.top >= card.bottom - 1;
            }), `${label} floating note overlaps preview at ${width}`);
        }
        results.layouts.push({label,viewport:width,...metrics});
    }
}
async function screenshot(page, label) {
    for(const [suffix,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
        await page.setViewportSize({width,height}); await page.evaluate(()=>scrollTo(0,0));
        await page.waitForTimeout(650);
        await page.screenshot({path:path.join(directory,`${label}-${suffix}.png`),fullPage:true});
    }
}
async function login(label) {
    const page = await create(); await page.goto(base+'/login');
    await page.locator('[name=login]').fill(accounts[label].email);await page.locator('[name=password]').fill(accounts[label].password);
    await page.getByRole('button',{name:'Login',exact:true}).click();
    await page.locator('[name=code]').fill(accounts[label].recovery);
    await page.getByRole('button',{name:'Verify & Sign In'}).click();await page.waitForURL('**/dashboard');
    return page;
}
(async()=>{
    fs.mkdirSync(directory,{recursive:true});
    browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    const page = await create();await page.goto(base+'/');
    const hero = page.locator('[data-fc-carousel]');
    // Real-time automatic transition, pause on hover and focus, explicit pause, looping.
    await page.mouse.move(0,0); await page.waitForTimeout(7000);
    assert.equal(await hero.getAttribute('data-active-slide'),'1');
    await hero.hover(); const hoverIndex=await hero.getAttribute('data-active-slide');await page.waitForTimeout(6700);assert.equal(await hero.getAttribute('data-active-slide'),hoverIndex);
    await hero.locator('[data-fc-next]').focus();await page.mouse.move(0,0);await page.waitForTimeout(6700);assert.equal(await hero.getAttribute('data-active-slide'),hoverIndex);
    await page.keyboard.press('End');assert.equal(await hero.getAttribute('data-active-slide'),'2');
    await hero.locator('[data-fc-next]').click();assert.equal(await hero.getAttribute('data-active-slide'),'0');
    await page.locator('[data-theme-toggle]').focus();await page.mouse.move(0,0);await page.waitForTimeout(6700);assert.equal(await hero.getAttribute('data-active-slide'),'0');
    results.interactions.push('Hero auto advance, hover/focus pause, keyboard End, looping and persistent manual pause');
    for(const value of ['light','dark']) {
        await theme(page,value);
        for(let index=0;index<3;index++) {
            await hero.locator(`[data-fc-go="${index}"]`).click();
            await layouts(page,`hero-${index+1}-${value}`);
            await screenshot(page,`hero-${index+1}-${value}`);
        }
    }
    // Layout remains fixed when changing slides at each phone/desktop width.
    for(const width of [320,390,768,1440]) {
        await page.setViewportSize({width,height:900});const heights=[];
        for(let i=0;i<3;i++){await hero.locator(`[data-fc-go="${i}"]`).click();heights.push(await hero.evaluate(e=>e.offsetHeight));}
        assert.equal(new Set(heights).size,1,`Hero height changed at ${width}`);
    }
    await page.reload();assert.equal(await page.evaluate(()=>document.documentElement.dataset.theme),'dark');
    await page.goto(base+'/login');assert.equal(await page.evaluate(()=>document.documentElement.dataset.theme),'dark');
    await screenshot(page,'login-dark');results.interactions.push('Theme saved across reload and navigation; constant hero height at 320/390/768/1440');
    const system = await create({colorScheme:'dark'});await system.goto(base+'/');assert.equal(await system.evaluate(()=>document.documentElement.dataset.theme),'dark');
    await system.emulateMedia({colorScheme:'light'});await system.waitForFunction(()=>document.documentElement.dataset.theme === 'light');
    await system.locator('[data-theme-toggle]').click();await system.emulateMedia({colorScheme:'dark'});await system.emulateMedia({colorScheme:'light'});assert.equal(await system.evaluate(()=>document.documentElement.dataset.theme),'dark');
    await system.emulateMedia({reducedMotion:'reduce'});await system.locator('[data-fc-pause]').waitFor({state:'hidden'});const before=await system.locator('[data-fc-carousel]').getAttribute('data-active-slide');await system.waitForTimeout(6700);assert.equal(await system.locator('[data-fc-carousel]').getAttribute('data-active-slide'),before);
    results.interactions.push('System theme, manual override, reduced-motion disables autoplay');
    const blocked=await create();await blocked.addInitScript(()=>{Storage.prototype.getItem=()=>{throw new Error('blocked')};Storage.prototype.setItem=()=>{throw new Error('blocked')};});await blocked.goto(base+'/');await blocked.locator('[data-theme-toggle]').click();assert.equal(await blocked.evaluate(()=>document.documentElement.dataset.theme),'dark');
    const nojs=await create({javaScriptEnabled:false});await nojs.goto(base+'/');assert.equal(await nojs.locator('[data-fc-slide]').count(),3);assert.ok(await nojs.locator('[data-fc-slide]').nth(2).isVisible());results.interactions.push('Storage-blocked theme toggle and no-JavaScript content fallback');
    const member=await login('member');
    for(const count of [0,1,3]) {
        fixture(count);await member.goto(base+'/dashboard');
        assert.equal(await member.locator('.fc-bulletin [data-fc-slide]').count(),count);
        assert.equal(await member.locator('.fc-bulletin [data-fc-controls]').count(),count>1?1:0);
        if(!count)assert.ok(await member.getByText('No new announcements.').isVisible());
        for(const value of ['light','dark']) {
            await theme(member,value);await layouts(member,`dashboard-${count}-${value}`);
            if(count===3){
                const bulletin=member.locator('.fc-bulletin');
                await bulletin.locator('[data-fc-go="2"]').click();await layouts(member,`dashboard-long-${value}`);
                await bulletin.locator('[data-fc-go="0"]').click();await screenshot(member,`dashboard-${value}`);
            }
        }
    }
    // Bulletin automation starts only while it is in view; manual navigation preserves reading time.
    await member.setViewportSize({width:1440,height:1000});await member.reload();const bulletin=member.locator('.fc-bulletin');await bulletin.scrollIntoViewIfNeeded();await member.mouse.move(0,0);await member.waitForTimeout(7500);assert.equal(await bulletin.getAttribute('data-active-slide'),'1');
    await bulletin.locator('[data-fc-next]').click();assert.equal(await bulletin.getAttribute('data-active-slide'),'2');
    await member.setViewportSize({width:390,height:844});await bulletin.scrollIntoViewIfNeeded();
    await bulletin.evaluate(element => {
        const start = new Touch({identifier:1,target:element,clientX:280,clientY:250});
        const end = new Touch({identifier:1,target:element,clientX:100,clientY:255});
        element.dispatchEvent(new TouchEvent('touchstart',{bubbles:true,touches:[start]}));
        element.dispatchEvent(new TouchEvent('touchend',{bubbles:true,changedTouches:[end]}));
    });
    assert.equal(await bulletin.getAttribute('data-active-slide'),'0');
    results.interactions.push('Dashboard 0/1/3 announcements, long title/excerpt, automatic rotation, manual control and horizontal swipe');
    const admin=await login('administrator');await admin.goto(base+'/dashboard/content-manager');
    for(const value of ['light','dark']){await theme(admin,value);await layouts(admin,`content-manager-${value}`);await screenshot(admin,`content-manager-${value}`);}
    const oldIcon=await admin.locator('link[rel=icon]').getAttribute('href');
    await admin.locator('#site-icon').setInputFiles(path.join(root,'public/images/site-icon.png'));
    assert.equal(await admin.locator('[data-icon-preview-label]').textContent(),'Unsaved preview');
    await admin.getByRole('button',{name:'Save Website Icon',exact:true}).click();await admin.getByText('Website icon has been updated.',{exact:true}).waitFor();
    assert.notEqual(await admin.locator('link[rel=icon]').getAttribute('href'),oldIcon);
    const response=await admin.request.get(await admin.locator('link[rel=icon]').getAttribute('href'));assert.equal(response.status(),200);assert.match(response.headers()['content-type'],/image\/png/);
    await admin.locator('#site-icon').setInputFiles({name:'bad.png',mimeType:'image/png',buffer:Buffer.from('not an image')});await admin.getByRole('button',{name:'Save Website Icon',exact:true}).click();await admin.locator('#site-icon-error').waitFor();
    await admin.locator('[name=restore_icon]').check();await admin.getByRole('button',{name:'Save Website Icon',exact:true}).click();await admin.getByText('Default website icon restored.',{exact:true}).waitFor();
    results.interactions.push('CMS current-icon preview, unsaved upload preview, versioned PNG upload, invalid upload error, restore default');
    // Shared dark-mode screens beyond the dashboard.
    for(const url of ['/dashboard/profile','/dashboard/security','/dashboard/members','/dashboard/directory','/dashboard/announcements','/dashboard/water','/dashboard/collections','/dashboard/inventory']) {
        await admin.goto(base+url);await theme(admin,'dark');await layouts(admin,url);
    }
    assert.deepEqual(results.errors,[]);
})().catch(error=>{results.errors.push(error.stack);process.exitCode=1;}).finally(async()=>{
    fs.writeFileSync(path.join(directory,'results.json'),JSON.stringify(results,null,2));
    console.log(JSON.stringify({layoutChecks:results.layouts.length,interactions:results.interactions,errors:results.errors},null,2));
    if(browser)await browser.close();
});
