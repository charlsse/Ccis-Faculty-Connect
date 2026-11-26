const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const dir = path.join(root, 'storage/app/private/workspace-updates-qa');
const accounts = JSON.parse(fs.readFileSync(path.join(root, 'storage/app/private/targeted-qa/auth-accounts.json')));
const base = 'http://127.0.0.1:8092';
const widths = [320,360,375,390,430,768,1024,1280,1440,1920];
const categories = ['branding','hero','preview','about','features','benefits','cta'];
const results = { layouts: [], interactions: [], errors: [] };
let browser;
async function category(page, key) {
    if (page.viewportSize().width <= 600) await page.locator('[data-category-select]').selectOption(key);
    else await page.locator(`[data-category="${key}"]`).click();
    assert.equal(await page.locator('[data-editor-panel]:visible').count(), 1);
    assert.equal(await page.locator(`[data-editor-panel="${key}"]`).isVisible(), true);
}
async function layout(page, label, width) {
    const problems = await page.evaluate(() => {
        const visible = el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
        return {overflow:document.documentElement.scrollWidth>innerWidth,
            clipped:[...document.querySelectorAll('.notification-panel,.profile-dropdown,main input:not([type=hidden]),main textarea,main select')].filter(visible).filter(el=>{const r=el.getBoundingClientRect();return r.left < -1 || r.right>innerWidth+1;}).map(el=>el.className || el.name)};
    });
    assert.equal(problems.overflow,false,`${label} overflow ${width}`);
    assert.deepEqual(problems.clipped,[],`${label} clipping ${width}`);
    results.layouts.push({label,width});
    if ([390,1440].includes(width)) {
        await page.waitForTimeout(280);
        await page.screenshot({path:path.join(dir,`${label}-${width}.png`),fullPage:false});
    }
}
(async()=>{
    fs.mkdirSync(dir,{recursive:true});
    browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    const context = await browser.newContext({viewport:{width:1440,height:1000},colorScheme:'light'});
    const page = await context.newPage();
    page.on('pageerror',e=>results.errors.push(e.message));
    page.on('response',r=>{if(r.request().isNavigationRequest()&&r.status()>=400)results.errors.push(`HTTP ${r.status()} ${r.url()}`);});
    await page.goto(base+'/login');
    await page.locator('[name=login]').fill(accounts.administrator.email);
    await page.locator('[name=password]').fill(accounts.administrator.password);
    await page.getByRole('button',{name:'Login',exact:true}).click();
    await page.locator('[name=code]').fill(accounts.administrator.recovery);
    await page.getByRole('button',{name:'Verify & Sign In'}).click();
    await page.waitForURL('**/dashboard');
    for(const theme of ['light','dark']) {
        await page.emulateMedia({colorScheme:theme});
        await page.goto(base+'/dashboard');
        for(const width of widths) {
            await page.setViewportSize({width,height:900});
            await layout(page,`dashboard-${theme}`,width);
            await page.locator('[data-profile-toggle]').click();
            await layout(page,`profile-${theme}`,width);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('[data-profile-toggle]').evaluate(el=>el===document.activeElement),true);
            await page.locator('[data-notification-toggle]').click();
            await layout(page,`notifications-${theme}`,width);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('[data-notification-toggle]').evaluate(el=>el===document.activeElement),true);
        }
        await page.goto(base+'/dashboard/notifications');
        for(const width of widths) {await page.setViewportSize({width,height:900});await layout(page,`history-${theme}`,width);}
        await page.goto(base+'/dashboard/content-manager');
        for(const width of widths) {
            await page.setViewportSize({width,height:900});
            for(const key of categories) {await category(page,key);await layout(page,`cms-${key}-${theme}`,width);}
        }
    }
    results.interactions.push('Both dropdowns open, close with Escape and restore keyboard focus at all ten widths in both themes');
    await page.setViewportSize({width:1440,height:1000});
    await category(page,'hero');
    const title=page.locator('[name="carousel_slides[0][title]"]');
    const original=await title.inputValue();
    await title.fill('Synthetic unsaved slide title');
    await category(page,'about');await category(page,'hero');
    assert.equal(await title.inputValue(),'Synthetic unsaved slide title');
    await page.locator('[data-category=hero]').focus();await page.keyboard.press('End');
    assert.equal(await page.locator('[data-category=cta]').getAttribute('aria-selected'),'true');
    await page.keyboard.press('Home');
    assert.equal(await page.locator('[data-category=branding]').getAttribute('aria-selected'),'true');
    await category(page,'hero');await title.fill('');await category(page,'about');
    await page.getByRole('button',{name:'Save Content',exact:true}).click();
    assert.equal(await page.locator('[data-editor-panel=hero]').isVisible(),true);
    assert.equal(await title.evaluate(el=>el===document.activeElement),true);
    await title.fill(original);
    await category(page,'about');
    const about=page.locator('[name="about[title]"]');const originalAbout=await about.inputValue();
    await about.fill('x'.repeat(161));await category(page,'features');
    await page.getByRole('button',{name:'Save Content',exact:true}).click();
    await page.locator('[name="about[title]"][aria-invalid=true]').waitFor();
    assert.equal(await page.locator('[data-editor-panel=about]').isVisible(),true);
    await about.fill(originalAbout);
    await category(page,'hero');
    await page.locator('[name="carousel_images[0]"]').setInputFiles({name:'workspace-qa-slide.png',mimeType:'image/png',buffer:fs.readFileSync(path.join(root,'public/images/site-icon.png'))});
    await category(page,'benefits');await category(page,'hero');
    assert.equal(await page.locator('[name="carousel_images[0]"]').evaluate(el=>el.files.length),1);
    await page.getByRole('button',{name:'Save Content',exact:true}).click();
    await page.getByText('Landing page content has been updated.',{exact:true}).waitFor();
    assert.equal(await page.locator('.editor-image-preview img').first().isVisible(),true);
    await page.locator('[name="carousel_slides[0][remove_image]"]').check();
    await page.getByRole('button',{name:'Save Content',exact:true}).click();
    await page.getByText('Landing page content has been updated.',{exact:true}).waitFor();
    results.interactions.push('All seven categories; arrow/Home/End tab keyboard; unsaved text and upload retention; hidden client invalid field reveal; server validation category reveal; full landing save and hero image upload/removal');
    await page.goto(base+'/dashboard/notifications?filter=security');
    assert.ok(await page.locator('.notification-history .notification-item').count()>0);
    await page.locator('.notification-history').getByRole('button',{name:'Mark as read',exact:true}).first().click();
    await page.getByRole('button',{name:'Mark all as read',exact:true}).first().click();
    assert.equal(await page.locator('.notification-count').count(),0);
    await page.goto(base+'/dashboard/notifications?filter=unread');
    await page.getByText('No notifications in this category.',{exact:true}).waitFor();
    results.interactions.push('Real category filtering, individual read, all read and unread badge removal');
    await page.setViewportSize({width:768,height:375});
    for (const kind of ['profile','notification']) {
        await page.locator(`[data-${kind}-toggle]`).click();
        const selector=kind==='profile'?'.profile-dropdown':'.notification-panel';
        assert.ok(await page.locator(selector).evaluate(el=>el.getBoundingClientRect().bottom<=innerHeight),`${kind} exceeds short landscape window`);
        await layout(page,`${kind}-landscape`,768);
        await page.keyboard.press('Escape');
    }
    await page.goto(base+'/dashboard/content-manager');await page.setViewportSize({width:390,height:844});
    await page.locator('[data-sidebar-open]').click();
    assert.equal(await page.locator('#dashboardSidebar').getByText('Logout',{exact:true}).count(),0);
    await page.keyboard.press('Escape');
    await page.locator('[data-profile-toggle]').click();
    assert.equal(await page.locator('.profile-dropdown form').count(),2);
    await page.getByRole('button',{name:'Logout',exact:true}).click();await page.waitForURL(base+'/');
    results.interactions.push('Mobile sidebar and account menu; normal logout remains operational');
    assert.deepEqual(results.errors,[]);
})().catch(error=>{results.errors.push(error.stack);process.exitCode=1;}).finally(async()=>{
    fs.writeFileSync(path.join(dir,'results.json'),JSON.stringify(results,null,2));
    console.log(JSON.stringify({checks:results.layouts.length,interactions:results.interactions,errors:results.errors},null,2));
    if(browser) await browser.close();
});
