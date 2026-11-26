const {chromium}=require('../../storage/app/private/water-qa/node_modules/playwright-core');const fs=require('node:fs'),assert=require('node:assert/strict');const dir='storage/app/private/workspace-glass-qa',base='http://127.0.0.1:8096';
(async()=>{const b=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});try{const c=await b.newContext({viewport:{width:1440,height:1000},storageState:`${dir}/treasurer-state.json`});const p=await c.newPage();
await p.goto(base+'/dashboard/water/members/3/pay');
if(!(await p.locator('a[href*="/receipts/"]').count())) {
await p.goto(base+'/dashboard/water/members/3/cash');await p.locator('[name=payment_time]').fill('10:00');await p.locator('[name=confirmed]').check();await p.getByRole('button',{name:'Review cash payment'}).click();await p.getByRole('button',{name:'Confirm and submit'}).click();await p.waitForLoadState('networkidle');assert.ok((await p.locator('body').textContent()).includes('receipt'));
}
await p.goto(base+'/dashboard/water/members/3/pay');
const receipt=p.url().includes('/receipts/')?p.url():await p.locator('a[href*="/receipts/"]').first().getAttribute('href');assert.ok(receipt);await p.goto(receipt);assert.ok((await p.locator('body').textContent()).includes('Verified payment'));
for(const theme of ['light','dark'])for(const width of [320,390,768,1440]){await p.evaluate(t=>document.documentElement.dataset.theme=t,theme);await p.setViewportSize({width,height:1000});await p.waitForTimeout(400);assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);if([390,1440].includes(width))await p.screenshot({path:`${dir}/receipt-${theme}-${width}.png`});}
await p.goto(base+'/dashboard/water/1');assert.equal((await p.locator('body').textContent()).includes('October faculty contribution'),true);await p.goto(base+'/dashboard/water/1/edit').then(r=>assert.equal(r.status(),403));
const faculty=await b.newContext({storageState:`${dir}/faculty-state.json`});const f=await faculty.newPage();await f.goto(base+'/dashboard/members').then(r=>assert.equal(r.status(),403));await f.goto(base+'/dashboard');await f.locator('[data-profile-toggle]').click();await f.getByRole('button',{name:'Logout',exact:true}).click();await f.waitForURL(base+'/');await f.goto(base+'/dashboard');assert.ok(f.url().includes('/login'));
console.log('PASS: isolated cash confirmation and verified receipt, 8 receipt layouts, locked published collection, faculty admin-route restriction, logout and protected redirect.');
}finally{await b.close()}})().catch(e=>{console.error(e);process.exit(1)});

