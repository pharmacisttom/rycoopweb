import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import {spawn} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import {createServer} from 'vite';
import react from '@vitejs/plugin-react';
import {chromium} from 'playwright';
const out=path.resolve('storage/tmp/staff-page-integration');await fs.mkdir(`${out}/sessions`,{recursive:true});
const token=randomBytes(32).toString('hex');
const php=process.env.PHP_BINARY|| (process.platform==='win32'?'C:/xampp/php/php.exe':'php');
const backend=spawn(php,['-S','127.0.0.1:5198','-t','public','tests/fixtures/StaffHttpRouter.php'],{env:{...process.env,STAFF_HTTP_FIXTURE_TOKEN:token,STAFF_HTTP_SESSION_PATH:`${out}/sessions`},stdio:'ignore'});
const server=await createServer({configFile:false,cacheDir:'storage/tmp/vite-staff-http',plugins:[react(),{name:'staff-session-fixture',configureServer(vite){vite.middlewares.use('/admin/users',async(request,response)=>{response.setHeader('Content-Type','text/html');response.end(await vite.transformIndexHtml('/admin/users','<html lang="th"><meta name="viewport" content="width=device-width,initial-scale=1"><body><div id="root"></div><script type="module" src="/tests/fixtures/StaffPage.jsx"></script></body></html>'));});}}],resolve:{alias:{'react-router-dom':'react-router'}},server:{host:'127.0.0.1',port:5199,strictPort:true,watch:{ignored:['**/public/app/**','**/storage/tmp/**']},proxy:{'/api':'http://127.0.0.1:5198','/__fixture':'http://127.0.0.1:5198','/csrf-token':'http://127.0.0.1:5198'}},optimizeDeps:{noDiscovery:true,include:['react','react-dom/client','react-router','lucide-react','dompurify','canvas-confetti','clsx']}});
let browser;
try{
  for(let attempt=0;attempt<50;attempt++){try{await fetch('http://127.0.0.1:5198/api/admin/staff/',{headers:{'X-Requested-With':'XMLHttpRequest'}});break;}catch{if(attempt===49)throw new Error('Fixture PHP server did not start');await new Promise(resolve=>setTimeout(resolve,100));}}
  await server.listen();browser=await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE?{executablePath:process.env.PLAYWRIGHT_EXECUTABLE}:{});
  const context=await browser.newContext({viewport:{width:1440,height:1000}});const request=context.request;
  async function session(mode){const response=await request.post('http://127.0.0.1:5199/__fixture/session',{headers:{'X-Fixture-Token':token},form:{mode}});assert.equal(response.status(),200);}
  for(const [mode,status] of [['guest',401],['pending',403],['member',403],['verified',200]]){
    await session(mode);const response=await request.get('http://127.0.0.1:5199/api/admin/staff/',{headers:{'X-Requested-With':'XMLHttpRequest'}});assert.equal(response.status(),status,mode);
    if(status===200){const payload=await response.json();assert.equal(payload.success,true);assert.ok(Array.isArray(payload.staff)&&Array.isArray(payload.invitations)&&Array.isArray(payload.roles));}
  }
  const page=await context.newPage();page.setDefaultTimeout(20000);const errors=[];page.on('pageerror',error=>errors.push(error.message));
  const paths=[];page.on('request',request=>{if(new URL(request.url()).pathname.startsWith('/api/admin/staff'))paths.push(new URL(request.url()).pathname);});
  await page.goto('http://127.0.0.1:5199/admin/users');await page.getByText('Fixture administrator',{exact:true}).last().waitFor();await page.getByText('Fixture editor',{exact:true}).waitFor();
  assert.ok(paths.length>0&&paths.every(url=>url==='/api/admin/staff/'),'real page uses exact trailing-slash list URL');
  assert.equal(await page.getByRole('table',{name:'ทะเบียนเจ้าหน้าที่และ MFA'}).count(),1);
  await page.screenshot({path:`${out}/authorized-staff.png`,fullPage:true,animations:'disabled',timeout:60000});
  for(const [status,payload] of [[404,{success:false,message:'Fixture staff API 404'}],[503,{success:false,message:'Fixture staff API unavailable'}],[200,{success:true,staff:null,invitations:[],roles:[]}]]){
    await page.route('**/api/admin/staff/',route=>route.fulfill({status,contentType:'application/json',body:JSON.stringify(payload)}));
    await page.reload();await page.getByRole('alert').filter({hasText:status===200?'รายชื่อเจ้าหน้าที่ไม่ครบถ้วน':payload.message}).waitFor();
    assert.equal(await page.getByRole('table',{name:'ทะเบียนเจ้าหน้าที่และ MFA'}).count(),0,'failure must not render an empty staff table');
    await page.screenshot({path:`${out}/staff-error-${status}.png`,fullPage:true,animations:'disabled',timeout:60000});await page.unroute('**/api/admin/staff/');
  }
  await page.reload();await page.getByText('Fixture editor',{exact:true}).waitFor();assert.deepEqual(errors,[]);
  console.log('PASS: real PHP-session guest/MFA/member guards, authorized /admin/users staff data, trailing-slash URL, unchanged contract and 404/503/malformed error states');
}finally{await browser?.close();await server.close();backend.kill();}
