// Static QA only: uses a separate Chrome profile, never the user's browser session.
const fs=require('fs'),path=require('path');
(async()=>{
 const root=path.resolve('storage/app/private/ui-preview');
 const port=fs.readFileSync(path.join(root,'chrome-render/DevToolsActivePort'),'utf8').split(/\r?\n/)[0];
 const targets=await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
 const target=targets.find(t=>t.type==='page');
 const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let next=0;const pending=new Map();ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id&&pending.has(m.id)){const {resolve,reject}=pending.get(m.id);pending.delete(m.id);m.error?reject(m.error):resolve(m.result)}});
 const call=(method,params={})=>new Promise((resolve,reject)=>{const id=++next;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}))});
 await call('Page.enable');
 const checks=[];
 for(const [name,width,height,label] of [['checkout',390,1100,'mobile'],['result',390,1100,'mobile'],['history',390,1100,'mobile'],['checkout',1440,1100,'desktop']]){
  await call('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
  await call('Page.navigate',{url:'file:///'+path.join(root,name+'-static.html').replaceAll('\\','/')});
  await new Promise(r=>setTimeout(r,600));
  const metrics=await call('Runtime.evaluate',{expression:'JSON.stringify({width:innerWidth,scroll:document.documentElement.scrollWidth})',returnByValue:true});
  checks.push({page:name,viewport:width,...JSON.parse(metrics.result.value)});
  const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(root,`${name}-${label}.png`),Buffer.from(shot.data,'base64'));
 }
 fs.writeFileSync(path.join(root,'layout-checks.json'),JSON.stringify(checks,null,2));console.log(JSON.stringify(checks));ws.close();
})().catch(e=>{console.error(e);process.exitCode=1});
