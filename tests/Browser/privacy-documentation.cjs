const {chromium}=require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs=require('node:fs'),assert=require('node:assert/strict');
const base='http://127.0.0.1:8096',dir='storage/app/private/workspace-glass-qa';
(async()=>{const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});let checks=0;try{
for(const role of ['public','admin','faculty']){
 const context=await browser.newContext(role==='public'?{}:{storageState:`${dir}/${role}-state.json`});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const paths=role==='public'?['/privacy','/login']:role==='admin'?['/dashboard/privacy-security','/dashboard/privacy-security/drafts/data-inventory']:['/dashboard/privacy-security'];
 for(const path of paths){await page.goto(base+path);assert.ok(!page.url().includes('/login')||path==='/login','Authenticated session required');
 for(const theme of ['light','dark']){await page.evaluate(t=>{localStorage.setItem('facultyconnect.theme',t);document.documentElement.dataset.theme=t},theme);
 for(const width of [320,375,390,430,768,1024,1440,1920]){await page.setViewportSize({width,height:900});await page.waitForTimeout(350);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${role} ${path} ${theme} ${width}: overflow`);checks++;
 if([390,1440].includes(width)){await page.screenshot({path:`${dir}/privacy-${role}-${path.split('/').pop()}-${theme}-${width}.png`,fullPage:true});}
 }
 }
 if(path.includes('privacy-security')&&!path.includes('drafts')){assert.equal(await page.locator('.privacy-internal').count(),role==='admin'?1:0);}
 if(path.includes('drafts')){await page.evaluate(()=>scrollTo(0,600));await page.waitForTimeout(200);assert.ok((await page.locator('.dashboard-topbar').boundingBox()).y<20);assert.notEqual(await page.locator('.fc-workspace-bar-material').evaluate(e=>getComputedStyle(e).backdropFilter),'none');await page.screenshot({path:`${dir}/privacy-admin-scroll.png`});}
 }
 if(role==='faculty')assert.equal((await page.goto(base+'/dashboard/privacy-security/drafts/data-inventory')).status(),403);
 if(role==='public')assert.equal((await page.goto(base+'/policies/privacy-notice')).status(),404);
 assert.deepEqual(errors,[]);await context.close();
}console.log(`${checks} responsive/theme checks passed; role boundaries, public draft denial and glass scroll verified.`);
}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});
