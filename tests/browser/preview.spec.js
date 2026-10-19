import {test,expect} from '@playwright/test';
for(const base of ['/','/test/']){
 test('routes, assets, API isolation '+base,async({page,request})=>{
  const apiRequests=[],failures=[];
  page.on('request',r=>{if(new URL(r.url()).pathname.includes('/api/'))apiRequests.push(r.url());});
  page.on('pageerror',e=>failures.push(e.message));
  await page.goto(base);
  await expect(page.getByRole('status').filter({hasText:'เว็บไซต์ประชาสัมพันธ์ตัวอย่าง'})).toBeVisible();
  const image=await request.get(base+'assets/img/logo.webp');expect(image.status()).toBe(200);expect(image.headers()['content-type']).toContain('image/webp');
  const missing=await request.get(base+'assets/no-such-image.webp');expect(missing.status()).toBe(404);
  for(const route of ['about','news','calculator','does-not-exist']){
   await page.goto(base+route);await page.waitForSelector('main');
   if(route==='does-not-exist')await expect(page.getByText('ไม่พบหน้าที่คุณต้องการ')).toBeVisible();
  }
  expect(failures).toEqual([]);expect(apiRequests).toEqual([]);
  for(const endpoint of ['api/auth/me','api/admin/members','api/member/dividends','login'])expect((await request.get(base+endpoint)).status()).toBe(403);
 });
 for(const width of [390,768,1440])test('responsive '+base+' '+width,async({page})=>{
  await page.setViewportSize({width,height:900});await page.goto(base);await page.waitForSelector('.navbar-container');
  await expect(page.locator('.navbar-brand-logo')).toBeVisible();
  expect(await page.locator('.navbar-brand-logo').evaluate(img=>img.complete&&img.naturalWidth>0)).toBe(true);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
  await page.screenshot({path:'test-results/'+(base==='/'?'main':'test')+'-'+width+'.png',fullPage:true});
 });
}
