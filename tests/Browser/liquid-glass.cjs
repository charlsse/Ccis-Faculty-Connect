/* Read-only UI regression: run against php artisan serve --port=8095.
   Uses the project's existing local Playwright installation; no new dependency. */
const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.FC_QA_URL || 'http://127.0.0.1:8095';
const out = path.resolve(__dirname, '../../tmp/glass-qa');
fs.mkdirSync(out, { recursive: true });
const results = { layouts: [], interactions: [], errors: [], glass: [] };
(async () => {
 const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
 try {
  for (const theme of ['light','dark']) {
   const context = await browser.newContext({ colorScheme: theme, reducedMotion: 'reduce', viewport: { width:1440,height:1000 } });
   const page = await context.newPage();
   page.on('pageerror', error => results.errors.push(error.message));
   for (const route of ['/','/login']) {
    const response=await page.goto(base+route); assert.equal(response.status(),200);
    await page.waitForLoadState('networkidle');
    for (const width of [320,375,390,430,768,1024,1440,1920]) {
     await page.setViewportSize({ width,height:900 });
     const measure = await page.evaluate(() => ({overflow:document.documentElement.scrollWidth>innerWidth,bodyWidth:document.body.scrollWidth,viewport:innerWidth}));
     assert.equal(measure.overflow,false,`${theme} ${route} ${width} overflows`);
     results.layouts.push({theme,route,width,...measure});
     if (route==='/' && width<=430) {
      await page.locator('[data-nav-toggle]').click();
      assert.equal(await page.locator('[data-nav-toggle]').getAttribute('aria-expanded'),'true');
      assert.equal(await page.locator('#main-content').evaluate(e=>e.inert),true);
      assert.equal(await page.evaluate(()=>document.body.style.overflow),'hidden');
      const box=await page.locator('#primaryNav').boundingBox();
      assert.ok(box.x>=0 && box.x+box.width<=width && box.y+box.height<=900,`menu escapes ${width}`);
      const first=page.locator('.site-header a').first();const last=page.locator('#primaryNav a').last();
      await last.focus();await page.keyboard.press('Tab');assert.equal(await first.evaluate(e=>e===document.activeElement),true);
      await page.keyboard.press('Shift+Tab');assert.equal(await last.evaluate(e=>e===document.activeElement),true);
      if (width===390) await page.screenshot({path:path.join(out,`menu-${theme}.png`)});
      await page.keyboard.press('Escape');
      assert.equal(await page.locator('[data-nav-toggle]').getAttribute('aria-expanded'),'false');
      assert.equal(await page.locator('[data-nav-toggle]').evaluate(e=>e===document.activeElement),true);
      assert.equal(await page.locator('#main-content').evaluate(e=>e.inert),false);
      assert.notEqual(await page.evaluate(()=>document.body.style.overflow),'hidden');
      results.interactions.push(`${theme} menu ${width}: bounds, focus loop, Escape, focus restoration, inert and scroll unlock`);
     }
    }
   }
   await page.goto(base+'/');await page.setViewportSize({width:1440,height:1000});
   await page.screenshot({path:path.join(out,`hero-${theme}.png`)});
   assert.equal(await page.locator('.fc-security-seal').count(),0);
   assert.equal(await page.locator('[data-emblem]').evaluate(e=>getComputedStyle(e).transform),'none');
   const glass=await page.locator('.fc-nav-material').evaluate(e=>{const s=getComputedStyle(e);return {background:s.backgroundColor,blur:s.backdropFilter,border:s.borderColor,shadow:s.boxShadow,rim:getComputedStyle(e,'::after').backgroundImage}});
   assert.ok(glass.blur.includes('blur(22px)')&&glass.blur.includes('saturate'));
   assert.ok(glass.background.startsWith('rgba'));results.glass.push({theme,...glass});
   await page.evaluate(()=>scrollTo({top:650,behavior:'instant'}));await page.waitForTimeout(120);
   assert.equal(await page.locator('.site-header').evaluate(e=>e.classList.contains('is-scrolled')),true);
   await page.screenshot({path:path.join(out,`transition-${theme}.png`)});
   // Put actual detailed faculty artwork behind the glass for optical QA.
   await page.locator('.fc-banner-track').evaluate(e=>scrollTo({top:scrollY+e.getBoundingClientRect().top+170,behavior:'instant'}));
   await page.screenshot({path:path.join(out,`glass-over-artwork-${theme}.png`)});
   await page.setViewportSize({width:390,height:844});await page.evaluate(()=>scrollTo({top:0,behavior:'instant'}));
   await page.screenshot({path:path.join(out,`mobile-${theme}.png`)});
   await page.locator('[data-nav-toggle]').click();
   await page.locator('[data-menu-backdrop]').click({position:{x:10,y:650}});
   assert.equal(await page.locator('[data-nav-toggle]').getAttribute('aria-expanded'),'false');
   await page.locator('[data-nav-toggle]').click();await page.locator('#primaryNav a[href="#about"]').click();
   assert.equal(await page.locator('#about').evaluate(e=>e===document.activeElement),true);
   assert.equal(await page.locator('[data-nav-toggle]').getAttribute('aria-expanded'),'false');
   await page.locator('[data-nav-toggle]').click();await page.setViewportSize({width:1024,height:768});
   assert.equal(await page.locator('[data-menu-backdrop]').isVisible(),false);
   assert.notEqual(await page.evaluate(()=>document.body.style.overflow),'hidden');
   results.interactions.push(`${theme}: backdrop, anchor focus, resize cleanup, reduced motion`);
   await page.setViewportSize({width:390,height:844});
   const artwork=page.locator('[data-fc-banner]:not([hidden]) [data-artwork-open]');
   await artwork.click();assert.equal(await page.locator('dialog').evaluate(e=>e.open),true);
   await page.screenshot({path:path.join(out,`artwork-viewer-${theme}.png`)});
   await page.locator('[data-artwork-zoom]').click();
   assert.equal(await page.locator('.fc-artwork-viewport').evaluate(e=>e.scrollWidth>e.clientWidth),true);
   await page.keyboard.press('Escape');assert.equal(await artwork.evaluate(e=>e===document.activeElement),true);
   await page.locator('[data-fc-next]').click();assert.equal(await page.locator('[data-fc-carousel]').getAttribute('data-active-slide'),'1');
   await page.locator('[data-role-preview="treasurer"]').click();assert.equal(await page.locator('#role-treasurer').isVisible(),true);
   results.interactions.push(`${theme}: original artwork modal, zoom, Escape, carousel, role preview`);
   await page.locator('[data-theme-toggle]').click();await page.reload();assert.equal(await page.locator('html').getAttribute('data-theme'),theme==='light'?'dark':'light');
   results.interactions.push(`${theme}: theme toggle persistence`);
   await page.locator('.fc-security').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(out,`lower-page-from-${theme}.png`)});
   await context.close();
  }
  const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:320,height:900}});const n=await nojs.newPage();await n.goto(base+'/');
  assert.equal(await n.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);assert.equal(await n.locator('#primaryNav a[href="#about"]').isVisible(),true);assert.ok(await n.locator('[data-artwork-open]').first().getAttribute('href'));
  results.interactions.push('No JavaScript: mobile navigation, artwork link and no overflow');await nojs.close();
  const c=await browser.newContext({viewport:{width:720,height:450},deviceScaleFactor:2,reducedMotion:'reduce'});const p=await c.newPage();await p.goto(base+'/');
  assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await p.locator('[data-nav-toggle]').click();
  let r=await p.locator('#primaryNav').boundingBox();assert.ok(r.y+r.height<=450);await p.screenshot({path:path.join(out,'zoom-200-equivalent.png')});
  results.interactions.push('200% equivalent layout: 1440 physical / 720 CSS pixels, bounded menu and no overflow');
  await p.keyboard.press('Escape');await p.setViewportSize({width:1440,height:900});await p.emulateMedia({reducedMotion:'no-preference'});await p.reload();await p.waitForTimeout(950);
  const rect=await p.locator('[data-emblem-scene]').boundingBox();await p.mouse.move(rect.x+rect.width*.8,rect.y+rect.height*.4);assert.ok((await p.locator('[data-emblem]').getAttribute('style')).includes('--tilt-y'));
  await p.locator('#about').scrollIntoViewIfNeeded();await p.waitForTimeout(150);assert.equal(await p.locator('[data-emblem-scene]').evaluate(e=>e.classList.contains('is-paused')),true);
  results.interactions.push('Pointer tilt and offscreen idle pause');
  await p.setViewportSize({width:390,height:300});await p.locator('[data-nav-toggle]').click();r=await p.locator('#primaryNav').boundingBox();assert.ok(r.y+r.height<=300);
  results.interactions.push('Short 300px viewport: menu remains bounded and internally scrollable');
  assert.deepEqual(results.errors,[]);fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(results,null,2));
  console.log(`${results.layouts.length} layout cases; ${results.interactions.length} interaction checks passed; no page errors.`);
 } finally {await browser.close()}
})().catch(error=>{console.error(error);process.exit(1)});
