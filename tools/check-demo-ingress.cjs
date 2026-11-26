'use strict';
const assert = require('node:assert/strict');
(async () => {
  const base = 'http://127.0.0.1:8005';
  for (const path of ['/', '/login', '/dashboard', '/.env', '/css/facultyconnect.css', '/webhooks/payments/xendit', '/payments/xendit/demo-return/not-a-uuid']) {
    const response = await fetch(base + path, {redirect:'manual'});
    assert.equal(response.status, 404, path);
    assert.equal(response.headers.get('set-cookie'), null);
  }
  const ref = '11111111-1111-4111-8111-111111111111';
  const response = await fetch(base + '/payments/xendit/demo-return/' + ref, {redirect:'manual'});
  assert.equal(response.status, 303);
  assert.equal(response.headers.get('location'), require('./presentation-origin.cjs')() + '/dashboard/water/demo/' + ref);
  const callback = await fetch(base + '/webhooks/payments/xendit-demo', {method:'POST', headers:{'content-type':'application/json'}, body:'{}'});
  assert.equal(callback.status, 503);
  assert.equal(callback.headers.get('set-cookie'), null);
  assert.equal(await callback.text(), 'Verification unavailable');
  console.log('PASS: seven forbidden paths, fixed local return, unauthenticated callback and cookie/debug stripping.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
