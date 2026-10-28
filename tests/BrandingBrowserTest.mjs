import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';
const require = createRequire(path.resolve(process.env.PLAYWRIGHT_PACKAGE_ROOT || '.', 'package.json'));
const { chromium } = require('playwright');
const out = 'storage/tmp/branding-qa';await fs.mkdir(out,{recursive:true});
const server = await createServer({configFile:false,plugins:[react(),{name:'branding-fixture',configureServer(vite){vite.middlewares.use('/__branding_fixture',async(request,response)=>{
  response.setHeader('Content-Type','text/html');response.end(await vite.transformIndexHtml('/__branding_fixture','<html lang="th"><meta name="viewport" content="width=device-width, initial-scale=1"><body><div id="root"></div><script type="module" src="/tests/fixtures/Branding.jsx"></script></body></html>'));
});}}],server:{host:'127.0.0.1',port:5194,strictPort:true},optimizeDeps:{include:['react','react-dom/client']}});
let browser;
try {
  await server.listen();browser=await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE?{executablePath:process.env.PLAYWRIGHT_EXECUTABLE}:{});
  const page=await browser.newPage();page.setDefaultTimeout(15000);const errors=[];page.on('pageerror',error=>errors.push(error.message));
  for(const theme of ['light','dark'])for(const width of [360,390,768,1440]) {
    await page.setViewportSize({width,height:1000});await page.goto(`http://127.0.0.1:5194/__branding_fixture?theme=${theme}`);
    await page.waitForFunction(()=>document.querySelectorAll('.coop-brand-logo').length===5&&[...document.querySelectorAll('.coop-brand-logo')].every(image=>image.complete&&image.naturalWidth>0));
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`no overflow ${theme}/${width}`);
    assert.ok(await page.locator('.coop-brand-logo').evaluateAll(images=>images.every(image=>getComputedStyle(image).objectFit==='contain'&&getComputedStyle(image).visibility==='visible'&&image.getBoundingClientRect().width>=38)));
    const response=await page.request.get(new URL(await page.locator('.coop-brand-logo').first().getAttribute('src'),page.url()).href);assert.equal(response.status(),200);assert.ok(response.headers()['content-type'].includes('image/webp'));
    await page.screenshot({path:`${out}/brand-${theme}-${width}.png`,fullPage:true});
  }
  assert.deepEqual(errors,[]);console.log('PASS: official emblem HTTP 200/image-webp, visible contain layout, 4 viewports × 2 themes, no JS errors');
}finally{await browser?.close();await server.close();}
