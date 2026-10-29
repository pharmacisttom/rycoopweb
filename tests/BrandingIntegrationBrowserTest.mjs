import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';
const require=createRequire(path.resolve(process.env.PLAYWRIGHT_PACKAGE_ROOT||'.','package.json'));
const {chromium}=require('playwright');const before=process.env.BRANDING_BASELINE==='1';
const phase=before?'before':'after',out=`storage/tmp/branding-qa/${phase}`;await fs.mkdir(out,{recursive:true});
const baselineFiles=new Set(['layout/Navbar','layout/Footer','auth/MfaAuthShell','admin/AdminControlCenterShell','member/AdminMemberShell','dashboard/DashboardHeader','common/PageSkeletonLoader','member/AuthModal'].map(name=>`src/components/${name}.jsx`).concat('src/pages/LoginPage.jsx'));
const fixturePlugin={name:'isolated-branding-presentations',enforce:'pre',load(id){const relative=path.relative(process.cwd(),id).replaceAll('\\','/');
  if(relative==='src/context/AuthContext.jsx')return 'export const useAuth=()=>({user:window.__fixtureKind==="login"?null:{id:900,name:"Synthetic staff",role:"super_admin"},isLoggedIn:false,can:()=>true,logout:async()=>{},sessionStatus:"guest"});';
  if(relative==='src/components/analytics/VisitorCounter.jsx')return 'export default function FixtureCounter(){return null;}';
  if(before&&baselineFiles.has(relative))return execFileSync('git',['show',`8ac8202:${relative}`],{encoding:'utf8'});
},configureServer(vite){vite.middlewares.use('/__integration_fixture',async(request,response)=>{
  response.setHeader('Content-Type','text/html');response.end(await vite.transformIndexHtml('/__integration_fixture','<html lang="th"><meta name="viewport" content="width=device-width, initial-scale=1"><body><div id="root"></div><script type="module" src="/tests/fixtures/BrandingIntegration.jsx"></script></body></html>'));
});vite.middlewares.use('/api/auth/options',(request,response)=>{response.setHeader('Content-Type','application/json');response.end('{"success":true,"google_enabled":false}');});}};
const server=await createServer({configFile:false,plugins:[fixturePlugin,react()],publicDir:'public',resolve:{alias:{'react-router-dom':'react-router'}},server:{host:'127.0.0.1',port:5195,strictPort:true},optimizeDeps:{include:['react','react-dom/client','react-router']}});
let browser;const findings=[];
try{
  await server.listen();browser=await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE?{executablePath:process.env.PLAYWRIGHT_EXECUTABLE}:{});
  const page=await browser.newPage();page.setDefaultTimeout(15000);const errors=[];page.on('pageerror',error=>errors.push(error.message));
  for(const theme of ['light','dark'])for(const width of [360,390,768,1440])for(const kind of ['navbar','footer','mfa','admin','member','dashboard','login']){
    await page.setViewportSize({width,height:1000});await page.goto(`http://127.0.0.1:5195/__integration_fixture?kind=${kind}&theme=${theme}`);
    if(kind==='admin'&&width<920)await page.getByRole('button',{name:'เปิดเมนูผู้ดูแลระบบ',exact:true}).click();
    if(!before)await page.waitForFunction(()=>document.querySelectorAll('.coop-brand-logo').length>0&&[...document.querySelectorAll('.coop-brand-logo')].every(image=>image.complete&&image.naturalWidth>0));
    findings.push({kind,theme,width,overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)});
    await page.screenshot({path:`${out}/${kind}-${theme}-${width}.png`,fullPage:true});
  }
  assert.deepEqual(errors,[]);await fs.writeFile(`${out}/findings.json`,JSON.stringify(findings,null,2));
  console.log(`PASS: ${phase} screenshots of 7 actual presentation components, 4 widths × 2 themes (isolated synthetic fixture, not authenticated TEST)`);
}finally{await browser?.close();await server.close();}
