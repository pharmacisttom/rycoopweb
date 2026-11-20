import { chromium } from 'playwright';

const base = process.env.RC15_BROWSER_BASE || 'http://127.0.0.1:4173';
const browser = await chromium.launch({ headless: true });
const output = [];
let failures = 0;
try {
  const context = await browser.newContext({viewport:{width:1366,height:850},reducedMotion:'reduce'});
  const page = await context.newPage();
  const errors = [];
  const network = [];
  const browserConsole = [];
  page.on('console', m => {if(m.type()==='error')browserConsole.push(m.text());});
  page.on('response', r => {if(r.status()>=400)network.push({url:r.url(),status:r.status()});});
  page.on('pageerror', error => errors.push(error.message));
  await context.route('**/api/public/content', route => route.fulfill({
    status:200,contentType:'application/json',
    body:JSON.stringify({success:true,data:{
      coopInfo:{nameTh:'ทดสอบสหกรณ์',slogan:'ข้อมูลทดสอบ'},
      boardMembers:[],boardGroups:[],staffMembers:[],loanProducts:[],
      interestRates:{deposits:[],loans:[],depositSource:null,loanSource:null},
      publication:{}
    }})
  }));
  await context.route('**/api/public/home-composition', route => route.fulfill({
    status:200,contentType:'application/json',
    body:JSON.stringify({success:true,data:{
      order:['hero','highlights','rates_news','quick_services','statistics','products','calculator','welfare'],
      visible:Object.fromEntries(['hero','highlights','rates_news','quick_services','statistics','products','calculator','welfare'].map(x=>[x,true])),
      theme:'royal-blue',autoplay_seconds:6,
      slides:[],layers:Object.fromEntries(['hero','highlights','rates_news','quick_services','statistics','products','calculator','welfare'].map(x=>[x,{surface:'original',spacing:'comfortable'}]))
    }})
  }));
  const response=await page.goto(base+'/app/',{waitUntil:'domcontentloaded',timeout:20000});
  await page.waitForTimeout(1500);
  const heading=await page.locator('body').innerText();
  const hasReactCrash=heading.includes('เกิดข้อผิดพลาด')||heading.includes('Something went wrong');
  const hasRate=heading.includes('อัตราดอกเบี้ยเด่นวันนี้');
  const hasNews=heading.includes('ข่าวสาร');
  const pass=!errors.length&&!hasReactCrash&&hasRate&&hasNews&&response?.ok();
  output.push({name:'Homepage null interest source',pass,errors:errors.slice(0,4),status:response?.status(),hasRate,hasNews,body:heading.slice(0,400),network:network.slice(0,8),browserConsole:browserConsole.slice(-30)});
  if(!pass)failures++;
  // Intercept image URLs so art-direction logic can be tested without a media server.
  const tinyPng=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLytQAAAABJRU5ErkJggg==','base64');
  await context.route('**/storage/uploads/highlights/*.webp',route=>route.fulfill({status:200,contentType:'image/png',body:tinyPng}));
  // Test public reordering and highlight rendering against an API-bound response.
  await context.route('**/api/public/home-composition',route=>route.fulfill({
    status:200,contentType:'application/json',
    body:JSON.stringify({success:true,data:{
      order:['highlights','hero','rates_news','quick_services','statistics','products','calculator','welfare'],
      visible:Object.fromEntries(['hero','highlights','rates_news','quick_services','statistics','products','calculator','welfare'].map(x=>[x,true])),
      theme:'ocean',autoplay_seconds:6,
      slides:Array.from({length:5},(_,i)=>({id:'smoke-'+i,title:'ทดสอบ Highlight '+(i+1),body:'ข้อความทดสอบเท่านั้น',link:'/about',image_url:'/storage/uploads/highlights/desktop.webp',mobile_image_url:'/storage/uploads/highlights/mobile.webp'})),
      background:{image_url:'',mobile_image_url:'',overlay_opacity:60,position:'center'},
      layers:Object.fromEntries(['hero','highlights','rates_news','quick_services','statistics','products','calculator','welfare'].map(x=>[x,{surface:'original',spacing:'comfortable'}]))
    }})
  }));
  const second=await context.newPage();
  const secondErrors=[];
  second.on('pageerror',err=>secondErrors.push(err.message));
  await second.goto(base+'/app/',{waitUntil:'domcontentloaded',timeout:20000});
  await second.waitForTimeout(1300);
  const highlight=await second.locator('.home-highlight-slider').count();
  const documentOrder=await second.evaluate(()=>{
    const slider=document.querySelector('.home-highlight-slider');
    const hero=document.querySelector('.home-hero-title');
    return slider&&hero?slider.compareDocumentPosition(hero)&Node.DOCUMENT_POSITION_FOLLOWING:0;
  });
  const pass2=highlight===1&&documentOrder!==0&&!secondErrors.length;
  output.push({name:'Highlights & published DOM ordering',pass:pass2,highlight,documentOrder,errors:secondErrors.slice(0,4)});
  if(!pass2)failures++;
  const picture=second.locator('.home-highlight-picture');
  const desktopPath=await picture.locator('img').evaluate(img=>new URL(img.currentSrc).pathname);
  const desktopPass=desktopPath==='/storage/uploads/highlights/desktop.webp';
  await second.setViewportSize({width:390,height:844});
  await second.waitForTimeout(300);
  const mobilePath=await picture.locator('img').evaluate(img=>new URL(img.currentSrc).pathname);
  const mobilePass=mobilePath==='/storage/uploads/highlights/mobile.webp';
  const responsivePass=desktopPass&&mobilePass;
  output.push({name:'Desktop and mobile photographic sources',pass:responsivePass,desktopPath,mobilePath});
  if(!responsivePass)failures++;
  await context.close();
} finally {
  await browser.close();
}
for(const item of output)console.log(JSON.stringify(item));
if(failures)process.exit(1);
console.log('RC15_BROWSER_RUNTIME_NULL_SAFETY=PASS');
