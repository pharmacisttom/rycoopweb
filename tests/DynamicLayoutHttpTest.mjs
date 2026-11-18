import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import {spawn,execFileSync} from 'node:child_process';
import {request} from 'playwright';
import {createHmac,randomUUID} from 'node:crypto';

const dir=path.resolve('storage/tmp/dynamic-layout-http-'+randomUUID());await fs.mkdir(dir,{recursive:true});
const port=Number(process.env.DYNAMIC_LAYOUT_HTTP_PORT||5397),base=`http://127.0.0.1:${port}`;
const child=spawn('php',['-d',`upload_tmp_dir=${dir}`,'-d',`sys_temp_dir=${dir}`,'-S',`127.0.0.1:${port}`,'tests/fixtures/DynamicLayoutRouter.php'],{cwd:process.cwd(),windowsHide:true,env:{...process.env,DYNAMIC_LAYOUT_HTTP:'1',DYNAMIC_LAYOUT_FIXTURE_FILE:path.join(dir,'fixture.sqlite')},stdio:['ignore','pipe','pipe']});
let logs='';child.stderr.on('data',b=>{logs+=b;});const api=await request.newContext({baseURL:base});let token;const imageIds=[];
async function call(method,url,data,expected=200,csrf=true){const response=await api.fetch(url,{method,data,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest',...(csrf&&token?{'X-CSRF-TOKEN':token}:{})}});const body=await response.json();assert.equal(response.status(),expected,JSON.stringify(body));return body;}
function totp(slice){let bits='',bytes=[];for(const c of 'JBSWY3DPEHPK3PXP')bits+='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(c).toString(2).padStart(5,'0');for(let i=0;i+8<=bits.length;i+=8)bytes.push(parseInt(bits.slice(i,i+8),2));const counter=Buffer.alloc(8);counter.writeBigUInt64BE(BigInt(slice));const h=createHmac('sha1',Buffer.from(bytes)).update(counter).digest(),offset=h[19]&15;return String((h.readUInt32BE(offset)&0x7fffffff)%1000000).padStart(6,'0');}
try{
 let ready=false;for(let i=0;i<60;i++){try{if((await api.get('/csrf-token')).ok()){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}assert(ready,logs);
 const root='/api/admin/v1/layout';await call('GET',root+'/draft',undefined,401);await call('POST','/__fixture/login',{id:2});token=(await call('GET','/csrf-token')).token;
 let draft=(await call('GET',root+'/draft')).data;assert.equal(draft.state.sections.length,8);
 await call('POST',root+'/sections',{version:draft.version,title:'No CSRF',slug:'no-csrf'},419,false);
 await call('POST',root+'/categories',{version:draft.version,title:'หมวดกิจกรรม'});draft=(await call('GET',root+'/draft')).data;
 const category=draft.state.categories[0].id;
 await call('POST',root+'/sections',{version:draft.version,title:'รูปกิจกรรมของสหกรณ์',slug:'gallery-test',type:'gallery',config:{category_id:category}});draft=(await call('GET',root+'/draft')).data;
 const section=draft.state.sections.at(-1);await call('PATCH',root+'/sections/'+section.id,{version:draft.version,title:'รูปกิจกรรมของสหกรณ์ — ทดสอบ'});draft=(await call('GET',root+'/draft')).data;
 const album=(await call('POST',root+'/gallery/albums',{version:draft.version,title:'อัลบั้มจริงใน fixture',slug:'http-album',category_id:category})).data.item;draft=(await call('GET',root+'/draft')).data;
 const png=Buffer.from(execFileSync('php',['-r',"$i=imagecreatetruecolor(1200,600);imagepng($i);imagedestroy($i);"]));
 let response=await api.post(`${root}/gallery/albums/${album.id}/images`,{headers:{'X-CSRF-TOKEN':token,Accept:'application/json'},multipart:{version:String(draft.version),'images[]':{name:'not-executable.php.png',mimeType:'image/png',buffer:png}}});let uploaded=await response.json();assert.equal(response.status(),200,JSON.stringify(uploaded));imageIds.push(...uploaded.data.item.ids);const image=imageIds[0];
 response=await api.get(`${root}/images/${image}/thumb`);assert.equal(response.status(),200);assert.equal(response.headers()['content-type'],'image/webp');assert((await response.body()).subarray(0,4).equals(Buffer.from('RIFF')));
 await call('GET',`/api/public/v1/layout/images/999/${image}/full`,undefined,404);assert.equal((await call('GET','/api/public/v1/layout/albums')).data.total,0);
 draft=(await call('GET',root+'/draft')).data;
 response=await api.post(`${root}/gallery/albums/${album.id}/images`,{headers:{'X-CSRF-TOKEN':token,Accept:'application/json'},multipart:{version:String(draft.version),'images[]':{name:'fake.png',mimeType:'image/png',buffer:Buffer.from('<?php echo 1;?>')}}});assert.equal(response.status(),422);assert.equal((await call('GET',root+'/draft')).data.version,draft.version);
 const existingFiles=await fs.readdir(path.resolve('storage/private/layout-gallery'));
 response=await api.post(`${root}/gallery/albums/${album.id}/images`,{headers:{'X-CSRF-TOKEN':token,Accept:'application/json'},multipart:{version:String(draft.version),'images[0]':{name:'valid-first.png',mimeType:'image/png',buffer:png},'images[1]':{name:'invalid-second.png',mimeType:'image/png',buffer:Buffer.from('not an image')}}});assert.equal(response.status(),422);
 assert.deepEqual((await fs.readdir(path.resolve('storage/private/layout-gallery'))).sort(),existingFiles.sort(),'failed upload batch cleans files written before error');
 assert.equal((await call('GET',root+'/draft')).data.version,draft.version,'failed upload batch rolls back version');
 await call('POST',root+'/publish',{version:draft.version},403);await call('POST','/__fixture/login',{id:1});token=(await call('GET','/csrf-token')).token;draft=(await call('GET',root+'/draft')).data;
 await call('POST',root+'/publish',{version:draft.version},403);await call('POST','/api/auth/mfa/step-up',{code:totp(Math.floor(Date.now()/30000)),scope:'layout.publish'});
 const published=(await call('POST',root+'/publish',{version:draft.version})).data;const albums=(await call('GET','/api/public/v1/layout/albums')).data;assert.equal(albums.total,1);assert.equal(albums.items[0].title,'อัลบั้มจริงใน fixture');
 response=await api.get(albums.items[0].images[0].src);assert.equal(response.status(),200);assert.equal(response.headers()['content-type'],'image/webp');
 await call('POST',root+'/publish',{version:published.version},403);
 await call('DELETE',`${root}/gallery/albums/${album.id}/images/${image}`,{version:published.version});assert.equal((await call('GET','/api/public/v1/layout/albums/http-album')).data.images.length,1);
 console.log('PASS: real PHP HTTP routing, sessions, MFA, CSRF, JSON PATCH/DELETE, persisted SQLite CRUD, multipart image signatures/re-encoding/private-public access, immutable publication and replay rejection');
}finally{
 await api.dispose();child.kill();await new Promise(r=>{child.once('exit',r);setTimeout(r,2000);});
 for(const id of imageIds)for(const size of ['full','thumb'])await fs.rm(path.resolve('storage/private/layout-gallery',`${id}-${size}.webp`),{force:true});
 // Keep the isolated database and server log as review evidence, never alter application data.
 await fs.writeFile(path.join(dir,'server.log'),logs);
}
