import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {createRequire} from 'node:module';
import {createServer} from 'vite';
import react from '@vitejs/plugin-react';
const require=createRequire(import.meta.url);
const {chromium}=require('playwright');
const port=5212,host='http://127.0.0.1:'+port;
const fixturePlugin={name:'rc22-icon-qa',configureServer(server){
  server.middlewares.use('/__rc22_icon_qa',async(_,res)=>{
    res.setHeader('Content-Type','text/html;charset=utf-8');
    res.end(await server.transformIndexHtml('/__rc22_icon_qa',
      '<!doctype html><html lang="th"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="root"></div><script type="module" src="/tests/fixtures/RC22IconBrowserFixture.jsx"></script></body></html>'));
  });
}};
const server=await createServer({configFile:false,plugins:[fixturePlugin,react()],
  resolve:{alias:{'react-router-dom':'react-router'}},
  server:{host:'127.0.0.1',port,strictPort:true,watch:{ignored:['**/public/app/**','**/storage/tmp/**']}},optimizeDeps:{noDiscovery:true,include:['react','react-dom/client','react/jsx-runtime','react/jsx-dev-runtime','react-router','lucide-react']}});
let browser;
try{
 await server.listen();
 browser=await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE?{executablePath:process.env.PLAYWRIGHT_EXECUTABLE}:{});
 await fs.mkdir('storage/tmp/rc22-icon-qa',{recursive:true});
 for(const viewport of [{width:390,height:844},{width:1280,height:800}]){
  const page=await browser.newPage({viewport,reducedMotion:'reduce'});
  const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  const failedAssets=[];
  page.on('response',response=>{if(response.status()>=400&&/simple-icons/.test(response.url()))failedAssets.push(response.url());});
  const responses=[];
  page.on('response',r=>{if(r.status()>=400)responses.push(r.status()+' '+new URL(r.url()).pathname);});
  await page.goto(host+'/__rc22_icon_qa');
  await page.getByTestId('rc22-shell').waitFor({timeout:12000}).catch(async()=>{
    throw new Error('RC22 fixture did not render; pageErrors='+JSON.stringify(errors)+
      ' httpErrors='+JSON.stringify(responses)+' html='+JSON.stringify((await page.locator('body').innerHTML()).slice(0,2000)));
  });
  for(const brand of ['google','facebook','line']){
    const icon=page.locator('img.ry-brand-icon[src*="'+brand+'"]');
    assert.equal(await icon.count(),1,brand+' should be a self-hosted SVG icon');
    assert.ok(await icon.evaluate(el=>el.complete&&el.naturalWidth>0),brand+' image failed to load');
    assert.equal(await icon.getAttribute('aria-hidden'),'true');
  }
  assert.equal(await page.locator('aside[aria-label="Admin modules"] svg').count(),3);
  assert.equal(await page.getByRole('link',{name:/คู่มือ/}).count(),1);
  assert.equal(await page.getByTestId('published-menu').getByRole('link').count(),2);
  await page.getByTestId('published-menu').getByRole('link',{name:/ประวัติและวิสัยทัศน์/}).click();
  assert.equal(await page.locator('html').getAttribute('data-menu-clicked'),'1');
  const width=await page.evaluate(()=>({scroll:document.documentElement.scrollWidth,client:document.documentElement.clientWidth}));
  assert.ok(width.scroll<=width.client+2,'horizontal overflow at width '+viewport.width+': '+JSON.stringify(width));
  assert.deepEqual(failedAssets,[]);
  assert.deepEqual(errors,[]);
  await page.screenshot({path:'storage/tmp/rc22-icon-qa/icons-'+viewport.width+'.png',fullPage:true});
  await page.close();
  console.log('RC22_PLAYWRIGHT_ICONS='+viewport.width+' PASS');
 }
 console.log('RC22_PLAYWRIGHT_PUBLIC_ADMIN_ICONS=PASS');
}finally{await browser?.close();await server.close();}
