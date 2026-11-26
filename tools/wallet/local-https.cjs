'use strict';
// Node receives the protected runtime.env through --env-file, never secret arguments.
const https = require('node:https');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = path.dirname(process.env.FC_WALLET_CONFIG || '');
const token = process.env.FC_LOCAL_TLS_TOKEN;
if (!token || token.length < 32 || !path.isAbsolute(root)) throw new Error('Private configuration required');
const server = https.createServer({
  key: fs.readFileSync(path.join(root, 'localhost.key')),
  cert: fs.readFileSync(path.join(root, 'localhost.crt')),
  minVersion: 'TLSv1.2',
}, (req, res) => {
  if (req.headers.host !== 'localhost:8444' || !['GET','POST','HEAD'].includes(req.method)) {
    res.writeHead(400); res.end(); return;
  }
  const headers = {...req.headers, host: 'localhost:8444', 'x-fc-sandbox-local-tls': token};
  for (const key of Object.keys(headers)) {
    if (key.startsWith('x-forwarded-') || key === 'forwarded' || key === 'connection') delete headers[key];
  }
  const upstream = http.request({hostname:'127.0.0.1',port:8003,path:req.url,method:req.method,headers}, response => {
    res.writeHead(response.statusCode, {...response.headers, 'x-content-type-options':'nosniff', 'referrer-policy':'same-origin'});
    response.pipe(res);
  });
  upstream.setTimeout(15000, () => upstream.destroy());
  upstream.on('error', () => {if (!res.headersSent) res.writeHead(503); res.end('Synthetic backend unavailable.');});
  req.pipe(upstream);
});
server.requestTimeout = 30000;
server.listen(8444, '127.0.0.1', () => console.log('Synthetic HTTPS listening on https://localhost:8444 (loopback only).'));
