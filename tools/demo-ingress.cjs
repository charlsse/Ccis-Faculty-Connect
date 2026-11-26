'use strict';
// Narrow loopback relay. The public tunnel must point here, never to port 8000.
const http = require('node:http');
const presentationOrigin = require('./presentation-origin.cjs');
const returnPath = /^\/payments\/xendit\/demo-return\/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/;
const server = http.createServer((req, res) => {
  const finish = (code, body) => { res.writeHead(code, {'content-type':'text/plain', 'cache-control':'no-store'}); res.end(body); };
  if (req.method === 'GET' && returnPath.test(req.url)) {
    let origin;
    try { origin = presentationOrigin(); } catch { return finish(503, 'Return unavailable'); }
    res.writeHead(303, {location: origin + '/dashboard/water/demo/' + returnPath.exec(req.url)[1], 'cache-control':'no-store', 'referrer-policy':'no-referrer'});
    res.end(); return;
  }
  if (req.method !== 'POST' || req.url !== '/webhooks/payments/xendit-demo') return finish(404, 'Not found');
  if (!(req.headers['content-type'] || '').toLowerCase().startsWith('application/json')) return finish(415, 'JSON required');
  let size = 0; const chunks = [];
  req.on('data', chunk => { size += chunk.length; if (size > 65536) { req.destroy(); return; } chunks.push(chunk); });
  req.on('end', () => {
    if (size > 65536) return;
    const upstream = http.request({hostname:'127.0.0.1', port:8000, path:'/webhooks/payments/xendit-demo', method:'POST',
      headers: {'host':'127.0.0.1:8000','content-type':'application/json','content-length':size,'x-callback-token':req.headers['x-callback-token'] || '', 'accept':'application/json'}}, response => {
        // Never propagate session cookies, debug bodies or arbitrary upstream headers.
        response.resume(); response.on('end', () => {
          console.log(JSON.stringify({time: new Date().toISOString(), event: 'demo_callback', upstream_status: response.statusCode, public_status: response.statusCode === 200 ? 200 : 503}));
          finish(response.statusCode === 200 ? 200 : 503, response.statusCode === 200 ? 'Received' : 'Verification unavailable');
        });
      });
    upstream.setTimeout(15000, () => upstream.destroy());
    upstream.on('error', () => {
      console.log(JSON.stringify({time: new Date().toISOString(), event: 'demo_callback', reason: 'upstream_transport_error', public_status: 503}));
      if (!res.headersSent) finish(503, 'Unavailable');
    });
    upstream.end(Buffer.concat(chunks));
  });
});
server.requestTimeout = 20000;
server.headersTimeout = 10000;
server.listen(8005, '127.0.0.1', () => console.log('Restricted demo ingress on loopback 8005; no public tunnel started.'));
