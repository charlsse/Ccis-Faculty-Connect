// Render-only copies of synthetic test responses. Never serve these as application routes.
const fs=require('fs'), path=require('path');
const dir='storage/app/private/ui-preview';
for(const name of ['checkout','history','result','simulation-receipt']) {
 let s=fs.readFileSync(`${dir}/${name}.html`,'utf8').replace(/<script\b[\s\S]*?<\/script>/gi,'');
 s=s.replace(/<link[^>]+href="http:\/\/127\.0\.0\.1:8000\/([^"?]+)[^"]*"[^>]*>/g,(tag,asset)=>asset.endsWith('.css')&&fs.existsSync('public/'+asset)?'<style>'+fs.readFileSync('public/'+asset,'utf8')+'</style>':tag);
 s=s.replace(/src="http:\/\/127\.0\.0\.1:8000\/(images\/[^"?]+)"/g,(_,asset)=>'src="file:///'+path.resolve('public/'+asset).replaceAll('\\','/')+'"');
 fs.writeFileSync(`${dir}/${name}-static.html`,s);
}
