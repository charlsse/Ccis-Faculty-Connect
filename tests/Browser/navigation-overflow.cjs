const {chromium}=require('../../storage/app/private/water-qa/node_modules/playwright-core');
const assert=require('node:assert/strict');
const dir='storage/app/private/workspace-glass-qa';
(async()=>{const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});try{
const context=await browser.newContext({storageState:`${dir}/admin-state.json`});const page=await context.newPage();await page.goto('http://127.0.0.1:8096/dashboard');assert.ok(!page.url().includes('login'));
let checks=0;
for(const theme of ['light','dark'])for(const [width,height] of [[1440,1000],[1024,600],[768,1024],[390,844],[320,568],[844,390],[1024,320]]){
 await page.setViewportSize({width,height});await page.evaluate(t=>{document.documentElement.dataset.theme=t},theme);
 if(width<=980)await page.locator('[data-sidebar-open]').click();await page.waitForTimeout(350);
 for(const selector of ['.dashboard-sidebar','.sidebar-scroll','.sidebar-nav'])assert.ok(await page.locator(selector).evaluate(e=>e.scrollWidth<=e.clientWidth+1),`${selector} horizontal ${width}`);
 assert.equal(await page.locator('.sidebar-scroll').evaluate(e=>getComputedStyle(e).scrollbarWidth),'none');
 const headY=await page.locator('.sidebar-head').evaluate(e=>e.getBoundingClientRect().y);
 await page.locator('.sidebar-scroll').evaluate(e=>e.scrollTop=e.scrollHeight);assert.equal(await page.locator('.sidebar-head').evaluate(e=>e.getBoundingClientRect().y),headY);
 await page.locator('.sidebar-nav a').last().focus();assert.ok(await page.locator('.sidebar-nav a').last().evaluate(e=>{const r=e.getBoundingClientRect(),s=e.closest('.sidebar-scroll').getBoundingClientRect();return r.top>=s.top-1&&r.bottom<=s.bottom+1}));
 if(width<=980)await page.keyboard.press('Escape');
 await page.locator('[data-profile-toggle]').click();await page.waitForTimeout(250);
 const menu=page.locator('[data-profile-menu]');const metrics=await menu.evaluate(e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return{x:r.x,y:r.y,right:r.right,bottom:r.bottom,sw:e.scrollWidth,cw:e.clientWidth,sh:e.scrollHeight,ch:e.clientHeight,bar:s.scrollbarWidth,blur:s.backdropFilter}});
 assert.ok(metrics.sw<=metrics.cw+1,`menu horizontal ${width}`);assert.ok(metrics.x>=11&&metrics.right<=width-11&&metrics.y>=11&&metrics.bottom<=height-11,JSON.stringify(metrics));assert.equal(metrics.bar,'none');assert.notEqual(metrics.blur,'none');
 if(height>=600)assert.ok(metrics.sh<=metrics.ch+1,'unnecessary menu scroll');
 await menu.locator('button').last().focus();assert.ok(await menu.locator('button').last().evaluate(e=>{let r=e.getBoundingClientRect(),s=e.closest('[data-profile-menu]').getBoundingClientRect();return r.bottom<=s.bottom+1&&r.top>=s.top}));
 if(height===320){await menu.hover();await page.mouse.wheel(0,-1500);await page.waitForTimeout(200);assert.equal(await menu.evaluate(e=>e.scrollTop),0);await page.keyboard.press('End');}
 await page.screenshot({path:`${dir}/navigation-${theme}-${width}x${height}.png`});await page.keyboard.press('Escape');assert.equal(await page.locator('[data-profile-toggle]').getAttribute('aria-expanded'),'false');checks++;
}
// Move only the test trigger near the bottom to exercise the upward placement branch.
await page.setViewportSize({width:390,height:844});await page.locator('.profile-menu').evaluate(e=>Object.assign(e.style,{position:'fixed',bottom:'20px',right:'20px'}));await page.locator('[data-profile-toggle]').click();await page.waitForTimeout(250);assert.equal(await page.locator('[data-profile-menu]').getAttribute('data-placement'),'above');await page.mouse.click(5,100);assert.equal(await page.locator('[data-profile-toggle]').getAttribute('aria-expanded'),'false');
console.log(`${checks} viewport/theme cases passed: no horizontal overflow, hidden tracks, fixed branding, reachable options, keyboard/wheel scrolling, glass and adaptive positioning.`);
await context.close();}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});
