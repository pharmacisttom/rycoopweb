import assert from 'node:assert/strict';
import fs from 'node:fs';
import React from 'react';
import { renderToPipeableStream } from 'react-dom/server';
import { StaticRouter } from 'react-router-dom/server.js';
import { Writable } from 'node:stream';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';
const mockPlugin={name:'workflow-test-contexts',enforce:'pre',load(id){
 const path=id.replaceAll('\\','/');
 if(path.endsWith('/src/context/AuthContext.jsx'))return 'export const useAuth=()=>globalThis.__workflowAuth;';
 if(path.endsWith('/src/context/ToastContext.jsx'))return 'export const useToast=()=>({toast:{},confirmDialog:async()=>false});';
 if(path.endsWith('/src/hooks/useInactivityTimeout.js'))return 'export const useInactivityTimeout=()=>({});';
 if(path.endsWith('/src/hooks/useVisitorTracker.js'))return 'export const useVisitorTracker=()=>{};';
 if(path.includes('/src/components/layout/')||/\/src\/components\/(?:common\/(?:AIChatWidget|CookieConsent|CampaignModal|SessionTimeoutModal|ChangePasswordModal|RichTextEditor)|member\/AuthModal)\.jsx$/.test(path))return 'export default function Fixture(){return null;}';
 }};
const server=await createServer({configFile:false,plugins:[mockPlugin,react()],optimizeDeps:{noDiscovery:true,include:[]},server:{middlewareMode:true},appType:'custom'});
function render(element){return new Promise((resolve,reject)=>{let html='';const output=new Writable({write(chunk,encoding,done){html+=chunk.toString();done();}});output.on('finish',()=>resolve(html));const stream=renderToPipeableStream(element,{onAllReady(){stream.pipe(output);},onError:reject});});}
try{
 const {default:App}=await server.ssrLoadModule('/src/App.jsx');
 for(const [url,permissions,expected] of [
  ['/admin/workspace',['news.view'],'จัดการข่าวประชาสัมพันธ์'],
  ['/admin/workspace',['rates.view'],'อัตราดอกเบี้ย'],
  ['/admin/public-content',['rates.view'],'จัดการข้อมูลเว็บไซต์สหกรณ์'],
  ['/admin/content?tab=news',['news.view'],'จัดการข่าวและคำถาม'],
  ['/admin/mfa/enroll',[],'ตั้งค่าการยืนยันตัวตนสองขั้นตอน'],
  ['/admin/mfa/verify',[],'ยืนยันตัวตนสองขั้นตอน'],
  ['/admin/2fa',[],'ยืนยันตัวตนสองขั้นตอน'],
  ['/admin/public-content',[],'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'],
 ]){
  globalThis.__workflowAuth={user:{id:2,name:'Fixture editor',role:'custom_editor'},isLoggedIn:true,sessionStatus:'online',can:key=>permissions.includes(key),logout:async()=>{},refreshSession:async()=>{}};
  const html=await render(React.createElement(StaticRouter,{location:url},React.createElement(App)));
  assert.ok(html.includes(expected),`${url} should render ${expected}`);
  if(url==='/admin/workspace'&&permissions.includes('news.view'))assert.ok(!html.includes('จัดการผู้ใช้งานและสิทธิ์'),'custom editor cannot see access management');
  console.log(`PASS: ${url} (${permissions.join(',')||'no module permission'})`);
 }
 const login=fs.readFileSync('src/pages/LoginPage.jsx','utf8');assert.ok(!login.includes('STAFF_ROLES'),'login has no custom-role allowlist');
 const auth=fs.readFileSync('src/context/AuthContext.jsx','utf8');assert.ok(auth.includes('refreshSession'),'MFA can rehydrate PHP identity');assert.ok(!auth.includes('window.location.reload'),'session probes never refresh the document');
 console.log('PASS: custom role login and session contracts');
}finally{delete globalThis.__workflowAuth;await server.close();}
