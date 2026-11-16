import assert from 'node:assert/strict';
import {createServer} from 'vite';
import react from '@vitejs/plugin-react';
import {chromium} from 'playwright';
const fixture={name:'board-workflow-fixture',configureServer(vite){vite.middlewares.use('/__board',async(req,res)=>{res.setHeader('Content-Type','text/html');res.end(await vite.transformIndexHtml('/__board','<html lang="th"><div id="root"></div><script type="module" src="/tests/fixtures/BoardTermWorkflow.jsx"></script></html>'));});}};
const server=await createServer({configFile:false,plugins:[fixture,react()],cacheDir:'storage/tmp/vite-board-workflow',server:{host:'127.0.0.1',port:0,watch:{ignored:['**/storage/tmp/**','**/public/app/**']}}});
let browser;
try {
 await server.listen();browser=await chromium.launch({executablePath:process.env.PLAYWRIGHT_EXECUTABLE});
 const page=await browser.newPage();const errors=[];const posts=[];page.on('pageerror',e=>errors.push(e.message));
 const member={id:10,name:'Fixture Director',position:'Chair',category:'director',image_url:'/uploads/custom.webp'};
 let terms=[];let failCopy=false;
 await page.route('**/csrf-token',route=>route.fulfill({json:{token:'fixture-csrf'}}));
 await page.route('**/api/admin/board-terms**',async route=>{
  const req=route.request(),url=new URL(req.url());let data={};
  if(req.method()==='POST'){assert.equal(req.headers()['x-csrf-token'],'fixture-csrf');posts.push(url.pathname);}
  if(url.pathname.endsWith('/import-38/preview')) data={directors:15,advisors:3,auditors:3,groups:3,boardMembers:[member],message:'Draft only'};
  else if(url.pathname.endsWith('/import-38')) {terms=[{id:1,term_number:38,term_name:'Term 38',status:'draft',is_current:false}];}
  else if(url.pathname.endsWith('/selection-candidates')) data={members:[member]};
  else if(url.pathname.endsWith('/selective-copy/preview')) {assert.deepEqual(req.postDataJSON().selections,[10]);data={members:[member],groups:[],version:'reviewed-snapshot'};}
  else if(url.pathname.endsWith('/selective-copy')) {
   assert.equal(req.postDataJSON().version,'reviewed-snapshot');
   if(failCopy){await route.fulfill({status:409,json:{success:false,message:'Preview is stale; refresh and confirm again'}});return;}
   terms.push({id:2,term_number:39,term_name:'Term 39',status:'draft',is_current:false});
  } else if(url.pathname.endsWith('/publish')) {terms=terms.map(t=>({...t,status:t.id===2?'published':'draft',is_current:t.id===2}));}
  else if(url.pathname.endsWith('/approve')) {} else data=terms;
  await route.fulfill({json:{success:true,data,message:'Draft saved'}});
 });
 await page.goto(server.resolvedUrls.local[0]+'__board');
 await page.getByRole('button',{name:'Import Preview: วาระ 38',exact:true}).click();
 await page.getByRole('region',{name:'Commit preview'}).waitFor();assert.equal(posts.length,0);
 page.on('dialog',d=>d.accept());
 await page.getByRole('button',{name:'ยืนยันบันทึก Draft เท่านั้น',exact:true}).click();
 await page.getByText('Draft / ร่าง',{exact:true}).waitFor();assert(!posts.some(p=>p.endsWith('/publish')));
 await page.getByRole('combobox').selectOption('1');await page.getByRole('checkbox').check();
 await page.getByRole('button',{name:'ดูตัวอย่างการคัดลอก',exact:true}).click();await page.getByText('Selective Copy Preview — Draft 39',{exact:true}).waitFor();
 failCopy=true;await page.getByRole('button',{name:'ยืนยันบันทึก Draft เท่านั้น',exact:true}).click();await page.getByRole('alert').filter({hasText:'Preview is stale'}).waitFor();
 failCopy=false;await page.getByRole('button',{name:'ยืนยันบันทึก Draft เท่านั้น',exact:true}).click();
 await page.getByText('Term 39:',{exact:false}).waitFor();assert(!posts.some(p=>p.endsWith('/publish')));
 await page.getByRole('button',{name:'Approve / อนุมัติ',exact:true}).nth(1).click();
 await page.getByRole('status').waitFor();assert(!posts.some(p=>p.endsWith('/publish')));
 await page.getByRole('button',{name:'Publish / เผยแพร่',exact:true}).nth(1).click();await page.getByText('Published / เผยแพร่',{exact:true}).waitFor();
 assert.deepEqual(errors,[]);console.log('PASS: browser import/copy preview, draft confirmation, CSRF, stale errors, separate approval/publication and status contracts');
} finally {await browser?.close();await server.close();}
