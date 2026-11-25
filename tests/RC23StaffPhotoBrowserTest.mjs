import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {createServer} from 'vite';
import react from '@vitejs/plugin-react';
const require=createRequire(import.meta.url);
const {chromium}=require('playwright');
const port=5223;
const mock={name:'rc23-staff-crud-fixture',enforce:'pre',load(id){
 const path=id.replaceAll('\\','/');
 if(path.endsWith('/src/context/PublicContentContext.jsx'))return `
  export const usePublicContent=()=>({catalog:{boardMembers:[]},reload:async()=>{}});
 `;
 if(path.endsWith('/src/services/memberAdminApi.js'))return `
  export async function memberAdminApi(path,{data}={}){
    window.__rc23Calls=window.__rc23Calls||[];
    window.__rc23Calls.push(path);
    if(path==='/api/admin/public-content/sections'){
      return {sections:window.location.search.includes('deny=1')?
        [{key:'profile',label:'ข้อมูลสหกรณ์',canEdit:true}]:
        [{key:'profile',label:'ข้อมูลสหกรณ์',canEdit:true},
         {key:'staff',label:'เจ้าหน้าที่',canEdit:true,canPublish:true},
         {key:'boards',label:'กรรมการ',canEdit:true,canPublish:true}]};
    }
    if(path==='/api/admin/public-content/sections/staff')return {
      value:[{id:11,name:'เจ้าหน้าที่ตัวอย่างหนึ่ง',position:'เภสัชกร',department:'ฝ่ายจัดการ',image:'/old-1.webp'},
             {id:12,name:'เจ้าหน้าที่ตัวอย่างสอง',position:'เจ้าหน้าที่',department:'ฝ่ายจัดการ',image:'/old-2.webp'}],
      version:'fixture-v1',history:[]
    };
    if(path==='/api/admin/public-content/sections/profile')return {
      value:{nameTh:'Fixture Cooperative'},version:'fixture-profile-v1',history:[]
    };
    if(path.endsWith('/drafts')){window.__staffDraft={path,data};return {id:202};}
    throw new Error('Unexpected fixture API '+path);
  }
 `;
 if(path.endsWith('/src/services/mediaLibraryApi.js'))return `
  export const mediaLibraryApi={uploadPeople:async(file,opts)=>{
    window.__portraitRequest={name:file.name,folder:opts.folder,alt:opts.altText};
    await new Promise(resolve=>setTimeout(resolve,80));
    return {data:{url:'/storage/uploads/staff/fixture-new.webp'}};
  }};
 `;
 if(path.endsWith('/src/utils/swal.js'))return `
  export const showConfirm=async()=>true;
  export const showDeleteConfirm=async()=>true;
  export const showSuccess=async()=>{};
  export const showError=async()=>{};
  export const showToast=()=>{};
  export const showInfo=()=>{};
  export const confirmPublish=async()=>true;
  export const showPrompt=async()=>null;
 `;
},configureServer(vite){
  vite.middlewares.use('/__rc23_staff',async(_,res)=>{
    res.setHeader('Content-Type','text/html;charset=utf-8');
    res.end(await vite.transformIndexHtml('/__rc23_staff',
      '<!doctype html><html lang="th"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="root"></div><script type="module" src="/tests/fixtures/RC23StaffPhotoFixture.jsx"></script></body></html>'));
  });
}};
const server=await createServer({configFile:false,plugins:[mock,react()],
 resolve:{alias:{'react-router-dom':'react-router'}},
 server:{host:'127.0.0.1',port,strictPort:true},
 optimizeDeps:{noDiscovery:true,include:['react','react-dom','react-dom/client','react-router','cookie','set-cookie-parser']}});
let browser;
try{
 await server.listen();
 browser=await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE?{executablePath:process.env.PLAYWRIGHT_EXECUTABLE}:{});
 const page=await browser.newPage({viewport:{width:1366,height:900},reducedMotion:'reduce'});
 const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:'+port+'/__rc23_staff');
 try {
   await page.getByRole('heading',{name:/ทำเนียบเจ้าหน้าที่/}).waitFor({timeout:15000});
 } catch (cause) {
   const diagnostics = await page.evaluate(() => ({
     url:location.href,
     title:document.title,
     visibleText:document.body?.innerText?.slice(0,1600),
     apiCalls:window.__rc23Calls||[],
   }));
   throw new Error('RC23 staff fixture did not render: '+JSON.stringify({diagnostics,pageErrors:errors}),{cause});
 }
 await page.waitForTimeout(200);
 const readCalls=await page.evaluate(()=>window.__rc23Calls.filter(x=>x.startsWith('/api/admin/public-content/sections')));
 assert.equal(readCalls.filter(x=>x==='/api/admin/public-content/sections').length,1,'section catalog must not refetch in a loop');
 assert.equal(readCalls.filter(x=>x==='/api/admin/public-content/sections/staff').length,1,'staff data must load once, no section feedback loop');
 await page.getByRole('button',{name:/เจ้าหน้าที่ตัวอย่างสอง/}).click();
 assert.equal(await page.getByRole('button',{name:/เลือกไฟล์เปลี่ยนรูปเจ้าหน้าที่/}).count(),1,'staff upload must have a visible file-picker button');
 const file=page.locator('#public-content-staff-photo');
 await file.setInputFiles({name:'person.png',mimeType:'image/png',
  buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=','base64')});
 await page.getByText(/อัปโหลดรูปบุคลากรแล้ว/).waitFor();
 assert.deepEqual(await page.evaluate(()=>window.__portraitRequest),{
   name:'person.png',folder:'staff',alt:'เจ้าหน้าที่ตัวอย่างสอง'
 });
 assert.equal(await page.getByLabel('ลิงก์รูปภาพ').count(),0,'portrait URL text input must be absent');
 assert.equal(await page.locator('figure.public-content-board-photo-preview img').getAttribute('src'),'/storage/uploads/staff/fixture-new.webp');
 await page.getByRole('button',{name:/เจ้าหน้าที่ตัวอย่างหนึ่ง/}).click();
 assert.equal(await page.locator('figure.public-content-board-photo-preview img').getAttribute('src'),'/old-1.webp','first staff photo must not be overwritten');
 await page.getByRole('button',{name:/เจ้าหน้าที่ตัวอย่างสอง/}).click();
 await page.getByLabel('เหตุผลการแก้ไข').fill('ทดสอบแก้รูปเจ้าหน้าที่');
 await page.getByRole('button',{name:'บันทึกร่างเพื่อรอเผยแพร่'}).click();
 await page.waitForFunction(()=>Boolean(window.__staffDraft));
 const draft=await page.evaluate(()=>window.__staffDraft);
 assert.equal(draft.path,'/api/admin/public-content/sections/staff/drafts');
 assert.equal(draft.data.value[0].image,'/old-1.webp');
 assert.equal(draft.data.value[1].image,'/storage/uploads/staff/fixture-new.webp');
 assert.deepEqual(errors,[]);
 await page.close();
 const denied=await browser.newPage();
 denied.on('pageerror',e=>errors.push(e.message));
 await denied.goto('http://127.0.0.1:'+port+'/__rc23_staff?deny=1');
 await denied.getByText(/ไม่พบหมวดข้อมูลที่ร้องขอ/).waitFor();
 const deniedStaffCalls=await denied.evaluate(()=>window.__rc23Calls.filter(x=>x==='/api/admin/public-content/sections/staff').length);
 assert.equal(deniedStaffCalls,0,'denied section must never load staff API');
 assert.equal(await denied.locator('#public-content-staff-photo').count(),0);
 assert.deepEqual(errors,[]);
 await denied.close();
 console.log('RC23_PLAYWRIGHT_STAFF_PHOTO_CRUD_AND_SECTION_ISOLATION=PASS');
}finally{await browser?.close();await server.close();}
