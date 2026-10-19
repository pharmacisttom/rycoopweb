import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
const mime={'.html':'text/html; charset=utf-8','.js':'application/javascript','.css':'text/css','.webp':'image/webp','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.svg':'image/svg+xml','.woff2':'font/woff2','.csv':'text/csv','.xlsx':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','.pdf':'application/pdf'};
export function createPreviewServer(directory=path.resolve('dist')) {
 return http.createServer((request,response)=>{
  let pathname;try{pathname=decodeURIComponent(new URL(request.url,'http://localhost').pathname);}catch{response.writeHead(400).end();return;}
  if(!['GET','HEAD'].includes(request.method)||/^\/(?:test\/)?(?:api|admin|member|staff|login|logout|csrf-token|change-password)(?:\/|$)/i.test(pathname)){response.writeHead(403).end('Preview services disabled');return;}
  const test=pathname==='/test'||pathname.startsWith('/test/');
  const root=path.resolve(directory,test?'test':'main');
  const local=test?pathname.replace(/^\/test\/?/,'/'):pathname;
  let file=path.resolve(root,'.'+local);
  if(!file.startsWith(root+path.sep)&&file!==root){response.writeHead(403).end();return;}
  if(local.split('/').some(x=>x.startsWith('.'))||/\.(?:php|env|sql|bak|ini|log)$/i.test(local)){response.writeHead(404).end();return;}
  if(!fs.existsSync(file)||!fs.statSync(file).isFile()) {
   if(path.extname(local)){response.writeHead(404).end('Not found');return;}
   file=path.join(root,'index.html');
  }
  if(!fs.existsSync(file)){response.writeHead(503).end('Build MAIN and TEST first');return;}
  response.writeHead(200,{'Content-Type':mime[path.extname(file)]||'application/octet-stream','X-Content-Type-Options':'nosniff','Cache-Control':'no-store'});
  if(request.method==='HEAD')response.end();else fs.createReadStream(file).pipe(response);
 });
}
if(process.argv[1]?.endsWith('staticPreviewServer.mjs'))createPreviewServer().listen(Number(process.env.PORT||4175),'127.0.0.1',()=>console.log('Preview http://127.0.0.1:'+(process.env.PORT||4175)));
