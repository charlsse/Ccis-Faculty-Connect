const { chromium } = require('../../storage/app/private/water-qa/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const colorScheme = process.env.FC_QA_THEME === 'dark' ? 'dark' : 'light';
const dir = path.resolve(__dirname, '../../storage/app/private/' + (colorScheme === 'dark' ? 'responsive-qa-dark' : 'responsive-qa'));
const accounts = JSON.parse(fs.readFileSync(path.join(dir, '../targeted-qa/auth-accounts.json')));
const base = 'http://127.0.0.1:8092';
const viewports = [[320,800],[360,800],[375,812],[390,844],[412,915],[430,932],[768,1024],[1024,768],
    [1280,720],[1366,768],[1440,900],[1536,864],[1600,900],[1920,1080]];
const results = { layouts: [], workflows: [], errors: [] };
let browser;
async function newPage() {
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 }, deviceScaleFactor: 1, colorScheme });
    const page = await context.newPage();
    page.on('pageerror', error => results.errors.push(error.message));
    page.on('response', response => {
        if (response.request().isNavigationRequest() && response.status() >= 400) results.errors.push(`HTTP ${response.status()} ${response.url()}`);
    });
    return page;
}
async function audit(page, label, shortAuth = false) {
    await page.waitForLoadState('networkidle');
    for (const [width,height] of viewports) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const measurement = await page.evaluate(() => {
            const visible = e => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden';
            const inputs = [...document.querySelectorAll('main input:not([type=hidden]):not([type=checkbox]), main select, main textarea')].filter(visible);
            const primary = document.querySelector('.auth-form [type=submit]');
            return { scrollWidth: document.documentElement.scrollWidth, scrollHeight: document.documentElement.scrollHeight,
                smallInputs: inputs.filter(e => parseFloat(getComputedStyle(e).fontSize) < 16).map(e => e.name),
                clippedControls: inputs.filter(e => e.getBoundingClientRect().right > innerWidth + 1 || e.getBoundingClientRect().left < -1).map(e => e.name),
                primaryBottom: primary?.getBoundingClientRect().bottom,
                toggleSizes: [...document.querySelectorAll('[data-password-toggle]')].filter(visible).map(e => ({width:e.offsetWidth,height:e.offsetHeight})),
                headingSize: document.querySelector('.auth-heading h2') ? getComputedStyle(document.querySelector('.auth-heading h2')).fontSize : null };
        });
        assert.ok(measurement.scrollWidth <= width, `${label} horizontal overflow at ${width}`);
        assert.deepEqual(measurement.smallInputs, [], `${label} small inputs at ${width}`);
        assert.deepEqual(measurement.clippedControls, [], `${label} clipped inputs at ${width}`);
        assert.ok(measurement.toggleSizes.every(s => s.width >= 44 && s.height >= 44), `${label} small toggle`);
        if (shortAuth && width >= 1024) {
            assert.ok(measurement.scrollHeight <= height + 1, `${label} short auth requires scroll at ${width}x${height}: ${measurement.scrollHeight}`);
            if (measurement.primaryBottom) assert.ok(measurement.primaryBottom <= height, `${label} submit below fold`);
        }
        results.layouts.push({ label, width, height, ...measurement });
        if ([390,1366].includes(width)) await page.screenshot({path:path.join(dir, `${label}-${width}.png`),fullPage:true});
    }
    await page.setViewportSize({width:1366,height:768});
}
async function login(label, complete = true) {
    const page = await newPage();
    await page.goto(base + '/login');
    await page.locator('[name=login]').fill(accounts[label].email);
    await page.locator('[name=password]').fill(accounts[label].password);
    await page.getByRole('button',{name:'Login',exact:true}).click();
    if (complete) {
        if(label === 'member') await audit(page,'2fa-challenge',true);
        await page.locator('[name=code]').fill(accounts[label].recovery);
        await page.getByRole('button',{name:'Verify & Sign In'}).click();
        await page.waitForURL('**/dashboard');
    }
    return page;
}
async function passwordFeedback(page) {
    const input = page.locator('[name=password]');
    const confirmation = page.locator('[name=password_confirmation]');
    const cases = [
        ['abc',['lower'],'Weak'],
        ['charlespassword',['length','lower'],'Fair'],
        ['Charlespassword123',['length','upper','lower','number'],'Good'],
        ['Charlespassword123!',['length','upper','lower','number','symbol'],'Strong'],
        ['Charlespogi123',['length','upper','lower','number'],'Good'],
        ['Äbcdefghijk١ ',['length','upper','lower','number','symbol'],'Good'],
        ['Abcdefghij1\t',['length','upper','lower','number'],'Good'],
    ];
    for (const [value,met,strength] of cases) {
        await input.fill(value);
        assert.deepEqual(await page.locator('[data-password-rule].is-met').evaluateAll(elements=>elements.map(e=>e.dataset.passwordRule)),met);
        assert.equal(await page.locator('[data-strength-label]').textContent(),strength);
    }
    await input.fill('Charlespassword123!');
    assert.equal(await page.locator('[data-password-match]').textContent(),'');
    await confirmation.fill('different');
    assert.equal(await page.locator('[data-password-match]').textContent(),'Passwords do not match');
    await confirmation.fill('Charlespassword123!');
    assert.equal(await page.locator('[data-password-match]').textContent(),'✓ Passwords match');
    const toggles=page.locator('[data-password-toggle]');
    await toggles.nth(0).focus(); await page.keyboard.press('Space');
    assert.equal(await input.getAttribute('type'),'text');
    assert.equal(await confirmation.getAttribute('type'),'password');
    await toggles.nth(1).focus(); await page.keyboard.press('Enter');
    assert.equal(await confirmation.getAttribute('type'),'text');
    await toggles.nth(0).click(); await toggles.nth(1).click();
    assert.equal(await input.inputValue(),'Charlespassword123!');
    await confirmation.fill(''); await input.fill('');
    assert.equal(await page.locator('[data-strength-label]').textContent(),'Not entered');
    results.workflows.push('Checklist/strength: requested examples, Unicode and tab; neutral empty state; match/mismatch; independent keyboard toggles preserve values');
}
async function navigation(page) {
    await page.setViewportSize({width:360,height:800});
    await page.getByRole('button',{name:'Open menu',exact:true}).click();
    assert.ok(await page.locator('#dashboardSidebar').evaluate(e=>e.classList.contains('open')));
    await page.getByRole('button',{name:'Close menu',exact:true}).click();
    assert.ok(await page.locator('#dashboardSidebar').evaluate(e=>!e.classList.contains('open')));
    results.workflows.push('Mobile navigation opens and closes');
}
(async()=>{
    fs.mkdirSync(dir,{recursive:true});
    browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    const guest=await newPage();
    await guest.goto(base+'/'); await audit(guest,'landing');
    await guest.goto(base+'/login'); await audit(guest,'login',true);
    await guest.goto(base+'/forgot-password'); await audit(guest,'forgot-password',true);
    await guest.goto(base+'/reset-password/'+accounts.reset.token+'?email='+encodeURIComponent(accounts.reset.email));
    await passwordFeedback(guest); await audit(guest,'reset-password',true);
    await guest.setViewportSize({width:390,height:844});
    await guest.locator('[name=password]').fill('Responsive!Password123'); await guest.locator('[name=password_confirmation]').fill('Responsive!Password123');
    await guest.getByRole('button',{name:'Reset Password',exact:true}).click(); await guest.waitForURL('**/login');
    results.workflows.push('Password reset submits successfully on phone');
    const temporary=await login('temporary',false); await audit(temporary,'temporary-password',true);
    await temporary.setViewportSize({width:360,height:800});
    await temporary.locator('[name=password]').fill('Responsive!Password123'); await temporary.locator('[name=password_confirmation]').fill('Responsive!Password123');
    await temporary.getByRole('button',{name:'Replace temporary password',exact:true}).click(); await temporary.waitForURL('**/verify-email');
    await audit(temporary,'verify-email',true);
    results.workflows.push('Temporary password submits successfully on phone');
    const setup=await login('setup',false); await audit(setup,'2fa-setup',true);
    const member=await login('member'); await audit(member,'member-dashboard'); await navigation(member);
    await member.goto(base+'/dashboard/profile'); await audit(member,'profile');
    await member.goto(base+'/dashboard/security'); await audit(member,'security'); await passwordFeedback(member);
    await member.goto(base+'/dashboard/collections'); await audit(member,'my-payments');
    await member.goto(base+'/dashboard/water'); await audit(member,'water-history');
    await member.goto(base+'/dashboard'); await member.getByRole('link',{name:'View Payment Instructions',exact:true}).click();
    await audit(member,'water-payment');
    await member.locator('[name=reference_number]').fill('RESPONSIVE-PREVIEW-ONLY');
    await member.locator('[name=payment_time]').fill('00:01');
    await member.locator('[name=proof]').setInputFiles(path.join(dir,'../targeted-qa/proof.png'));
    await member.locator('[name=confirmed]').check();
    await member.getByRole('button',{name:'Review submission',exact:true}).click();
    await member.locator('dialog').waitFor();
    await audit(member,'payment-confirmation');
    results.workflows.push('Payment confirmation opens with existing fields; preview only, no payment submitted');
    const admin=await login('administrator');
    for(const [url,label] of [['/dashboard','admin-dashboard'],['/dashboard/members','accounts'],['/dashboard/members/create','create-account'],['/dashboard/directory','directory'],['/dashboard/content-manager','content-manager'],['/dashboard/water/audit','water-audit']]) {
        await admin.goto(base+url); await audit(admin,label);
    }
    await admin.goto(base+'/dashboard/members/1'); await audit(admin,'account-details');
    const secretary=await login('secretary'); await audit(secretary,'secretary-dashboard');
    await secretary.goto(base+'/dashboard/announcements'); await audit(secretary,'announcements');
    const treasurer=await login('treasurer'); await audit(treasurer,'treasurer-dashboard');
    for(const [url,label] of [['/dashboard/collections','manage-payments'],['/dashboard/water/queue','verification-queue'],['/dashboard/water/accounts','payment-accounts'],['/dashboard/water/create','create-collection']]) {
        await treasurer.goto(base+url); await audit(treasurer,label);
    }
    // Existing isolated data includes a verified archived collection and receipt.
    await treasurer.goto(base+'/dashboard/water/1/report'); await audit(treasurer,'water-report');
    await treasurer.goto(base+'/dashboard/water/receipts/1');
    if((await treasurer.locator('.water-receipt').count())>0) await audit(treasurer,'water-receipt');
    assert.deepEqual(results.errors,[]);
})().catch(error=>{results.errors.push(error.message);process.exitCode=1;}).finally(async()=>{
    fs.writeFileSync(path.join(dir,'results.json'),JSON.stringify(results,null,2));
    console.log(JSON.stringify({checks:results.layouts.length,workflows:results.workflows,errors:results.errors},null,2));
    if(browser) await browser.close();
});
