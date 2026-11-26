require('node:child_process').execFileSync('php', ['tests/Browser/render-auth-preview.php']);
const {chromium}=require('../../storage/app/private/water-qa/node_modules/playwright-core');
const assert=require('node:assert/strict'),fs=require('node:fs');
fs.mkdirSync('tmp/auth-microdots-qa',{recursive:true});
(async()=>{const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});let cases=0;try{
for(const theme of ['light','dark'])for(const kind of ['login','onboarding']){
const context=await browser.newContext({viewport:{width:1440,height:1000},colorScheme:theme});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
if(kind==='onboarding')await page.route('**/onboarding/password',r=>r.fulfill({contentType:'text/html',body:fs.readFileSync('tmp/auth-onboarding-preview.html','utf8')}));
await page.goto(`http://127.0.0.1:8095/${kind==='login'?'login':'onboarding/password'}`);await page.waitForSelector('.fc-microdots.is-rendered');
await page.waitForTimeout(350);assert.equal(await page.locator('[data-microdots]').getAttribute('data-dot-phase'),'intro');await page.screenshot({path:`tmp/auth-microdots-qa/${kind}-${theme}-intro.png`});
await page.waitForTimeout(2100);assert.equal(await page.locator('[data-microdots]').getAttribute('data-dot-phase'),'ambient');
const a=await page.locator('canvas').evaluate(c=>c.toDataURL());await page.waitForTimeout(900);assert.notEqual(await page.locator('canvas').evaluate(c=>c.toDataURL()),a);await page.screenshot({path:`tmp/auth-microdots-qa/${kind}-${theme}-desktop.png`});
for(const width of [320,390,768,1440]){await page.setViewportSize({width,height:900});await page.waitForTimeout(180);const m=await page.locator('[data-microdots]').evaluate(e=>({width:e.clientWidth,height:e.clientHeight,count:+e.dataset.dotCount,overflow:document.documentElement.scrollWidth>innerWidth,pointer:getComputedStyle(e).pointerEvents}));assert.ok(m.width>=width-20&&m.width<=width);assert.ok(m.height>0&&m.count>500);assert.equal(m.overflow,false);assert.equal(m.pointer,'none');cases++;if(width===390)await page.screenshot({path:`tmp/auth-microdots-qa/${kind}-${theme}-mobile.png`})}
if(kind==='login'){await page.locator('[name=login]').fill('visual-check');await page.locator('[name=password]').fill('Synthetic!123');await page.locator('[data-password-toggle]').click();assert.equal(await page.locator('[name=password]').getAttribute('type'),'text');assert.ok(await page.locator('.auth-help-link').getAttribute('href'));assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true)}
await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(100);const still=await page.locator('canvas').evaluate(c=>c.toDataURL());await page.waitForTimeout(250);assert.equal(await page.locator('canvas').evaluate(c=>c.toDataURL()),still);assert.equal(await page.locator('.auth-panel').first().evaluate(e=>getComputedStyle(e).animationName),'none');assert.deepEqual(errors,[]);await context.close();
}console.log(`PASS: ${cases} responsive/theme cases; login and rendered onboarding template; opening wave, ambient motion, reduced motion, controls; no page errors.`)}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});


