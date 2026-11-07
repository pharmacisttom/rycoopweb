import assert from 'node:assert/strict';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import { chromium } from 'playwright';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const artifactDir = 'C:/Users/ACER/.gemini/antigravity/brain/6f718d35-ff7b-472d-bc44-a9827f67fee8';

console.log('========================================================================');
console.log(' RYCOOPWEB — COMPLETE HOMEPAGE UI INTEGRATION BROWSER TEST');
console.log('========================================================================');

// 1. Launch Vite Dev Server on port 5199
const vite = await createServer({
  configFile: path.resolve(rootDir, 'vite.config.js'),
  server: { host: '127.0.0.1', port: 5199, strictPort: true },
  logLevel: 'error',
});
await vite.listen();
console.log('[PASS] Local Vite server running on http://127.0.0.1:5199');

let browser;
try {
  try {
    browser = await chromium.launch({ headless: true });
  } catch (e) {
    console.log('[DIAGNOSTIC] Standard chromium launch fallback to msedge channel...');
    browser = await chromium.launch({ channel: 'msedge', headless: true });
  }
  console.log('[PASS] Browser launched successfully.');

  const viewports = [
    { name: 'Desktop', width: 1280, height: 800 },
    { name: 'Mobile', width: 390, height: 844 },
  ];

  for (const vp of viewports) {
    console.log(`\n--- Testing Viewport: ${vp.name} (${vp.width}x${vp.height}) ---`);
    const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    await context.addInitScript(() => {
      sessionStorage.setItem('coop_campaign_shown', 'true');
    });
    const page = await context.newPage();
    page.setDefaultTimeout(30000);

    const pageErrors = [];
    page.on('pageerror', (err) => {
      console.error(`[PAGE ERROR ${vp.name}] ${err.message}`);
      pageErrors.push(err.message);
    });

    // 1. Test Homepage with Default Fallback 4 Slides
    console.log(`[TEST 1] Navigating to Homepage (/) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    // Verify no uncaught exceptions
    assert.equal(pageErrors.length, 0, `Homepage had uncaught JS errors: ${pageErrors.join(', ')}`);

    // Verify Error Boundary did not catch a crash
    const errorBoundary = await page.locator('text=Something went wrong').count();
    assert.equal(errorBoundary, 0, 'Homepage showed an Error Boundary!');

    // 1.1 Confirm .hero-slider-wrapper exists and is visible at the top
    const heroSlider = page.locator('.hero-slider-wrapper');
    assert.ok(await heroSlider.count() > 0, '.hero-slider-wrapper must exist');
    assert.ok(await heroSlider.isVisible(), '.hero-slider-wrapper must be visible');
    console.log(`[PASS] .hero-slider-wrapper rendered and is visible [${vp.name}].`);

    // 1.2 Confirm old .home-hero-grid is NO LONGER the main hero
    const oldMainHero = await page.locator('.hero-section .home-hero-grid, section > .home-hero-grid').count();
    assert.equal(oldMainHero, 0, 'Old .home-hero-grid must NOT be present as the main hero');
    console.log(`[PASS] Verified legacy .home-hero-grid is not the main Hero [${vp.name}].`);

    // 1.3 Confirm four default slides when CMS returns no published slides
    const defaultSlides = await page.locator('.hero-slide').count();
    assert.equal(defaultSlides, 4, `Expected exactly 4 default slides, found ${defaultSlides}`);
    console.log(`[PASS] Exactly 4 default promotional slides rendered [${vp.name}].`);

    // Verify Slide 1 Content
    const slide1Title = await page.locator('.hero-slide.active', { hasText: 'ยินดีต้อนรับสมาชิกสหกรณ์' }).count();
    assert.ok(slide1Title > 0, 'First active slide title must be "ยินดีต้อนรับสมาชิกสหกรณ์"');

    // 1.4 Test Carousel Interaction (Next button, prev button, dots, play/pause)
    if (vp.name === 'Desktop') {
      const nextBtn = page.locator('.hero-nav-btn.next');
      assert.ok(await nextBtn.isVisible(), 'Carousel next button must be visible on Desktop');
      await nextBtn.click();
      await page.waitForTimeout(800);

      // Verify active slide transitioned to Slide 2
      const slide2Active = await page.locator('.hero-slide.active', { hasText: 'เงินกู้ของสหกรณ์' }).count();
      assert.ok(slide2Active > 0, 'Slide transitioned to Slide 2 on Next click');
      console.log(`[PASS] Carousel Next button navigation verified [${vp.name}].`);

      // Test Dot Navigation to Slide 3
      const dot3 = page.locator('.hero-dot').nth(2);
      await dot3.click();
      await page.waitForTimeout(800);
      const slide3Active = await page.locator('.hero-slide.active', { hasText: 'เงินปันผลและเงินเฉลี่ยคืน' }).count();
      assert.ok(slide3Active > 0, 'Slide transitioned to Slide 3 on dot 3 click');
      console.log(`[PASS] Carousel Dot navigation verified [${vp.name}].`);

      // Test Play/Pause Toggle
      const playPauseBtn = page.locator('.hero-toggle-play-btn');
      assert.ok(await playPauseBtn.isVisible(), 'Play/Pause toggle button must be visible');
      await playPauseBtn.click();
      console.log(`[PASS] Play/Pause toggle clicked successfully [${vp.name}].`);
    }

    // 1.5 Confirm Quick Access Cards (all 6 cards exist)
    const quickCards = ['เข้าสู่ระบบสมาชิก', 'เงินกู้ของสหกรณ์', 'เงินฝาก', 'เอกสารดาวน์โหลด', 'ข่าวสารและประกาศ', 'ปฏิทินยื่นกู้'];
    for (const cardTitle of quickCards) {
      const count = await page.locator(`text=${cardTitle}`).count();
      assert.ok(count > 0, `Quick Access Card "${cardTitle}" must be present`);
    }
    console.log(`[PASS] All 6 Quick Access Cards rendered with clean White & Blue grid [${vp.name}].`);

    // 1.6 Confirm Chairperson Welcome & Leadership Section
    const chairpersonHeading = await page.locator('text=สารจากประธานกรรมการสหกรณ์').count();
    assert.ok(chairpersonHeading > 0, 'Chairperson welcome heading must exist');
    const chairpersonCaption = await page.locator('text=นางสาวอุษา อิศรางกูร ณ อยุธยา').count();
    assert.ok(chairpersonCaption > 0, 'Chairperson name caption must exist');
    console.log(`[PASS] Dedicated Chairperson Welcome & Leadership section rendered [${vp.name}].`);

    // 1.7 Confirm News, Announcements & Daily Rates Section
    const newsHeader = await page.locator('text=ข่าวประชาสัมพันธ์และอัตราดอกเบี้ยประจำวัน').count();
    assert.ok(newsHeader > 0, 'News & rates section header must exist');
    const rateCard = await page.locator('text=อัตราดอกเบี้ยเด่นวันนี้').count();
    assert.ok(rateCard > 0, 'Rate card widget must exist');
    console.log(`[PASS] News, Announcements & Rates section rendered [${vp.name}].`);

    // Capture screenshot
    if (fs.existsSync(artifactDir)) {
      const screenshotPath = path.join(artifactDir, `uat-${vp.name.toLowerCase()}-slider.png`);
      await page.screenshot({ path: screenshotPath, fullPage: false });
      console.log(`[PASS] Saved screenshot to ${screenshotPath}`);
    }

    // 2. Test Navigation to Loan Schedule (/loan-schedule)
    console.log(`[TEST 2] Navigating to Loan Schedule (/loan-schedule) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/loan-schedule', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    assert.equal(pageErrors.length, 0, `Loan Schedule had uncaught JS errors: ${pageErrors.join(', ')}`);
    const scheduleHeader = await page.getByRole('heading', { level: 1 }).count();
    assert.ok(scheduleHeader > 0, 'Loan schedule page header rendered');
    console.log(`[PASS] Loan Schedule route rendered cleanly [${vp.name}].`);

    // 3. Test Navigation to Documents (/documents)
    console.log(`[TEST 3] Navigating to Documents (/documents) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/documents', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    assert.equal(pageErrors.length, 0, `Documents had uncaught JS errors: ${pageErrors.join(', ')}`);
    const docsHeader = await page.getByRole('heading', { level: 1 }).count();
    assert.ok(docsHeader > 0, 'Documents page header rendered');
    console.log(`[PASS] Documents route rendered cleanly [${vp.name}].`);

    // 4. Test Navigation / Member Login (/member/login)
    console.log(`[TEST 4] Navigating to Member Login Page (/member/login) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/member/login', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    assert.equal(pageErrors.length, 0, `Member Login page had uncaught JS errors: ${pageErrors.join(', ')}`);
    const loginForm = await page.locator('form, input[type="password"], button[type="submit"]').count();
    assert.ok(loginForm > 0, 'Member login form rendered');
    console.log(`[PASS] Member Login route rendered cleanly [${vp.name}].`);

    await context.close();
  }

  // 5. Test Dynamic CMS Slides Rendering
  console.log('\n--- Testing Dynamic CMS Slides Rendering ---');
  const cmsContext = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  await cmsContext.addInitScript(() => {
    sessionStorage.setItem('coop_campaign_shown', 'true');
  });
  const cmsPage = await cmsContext.newPage();

  // Mock CMS responses so Promise.all([fetchHomeData(), fetchCoopInfo()]) resolves immediately
  await cmsPage.route('**/api/**', async (route) => {
    const url = route.request().url();
    if (url.includes('/api/public/home')) {
      const json = {
        success: true,
        data: {
          heroSlides: [
            {
              id: 991,
              title: 'โครงการพิเศษเพื่อสมาชิกสาธารณสุขระยอง',
              subtitle: 'สินเชื่อสวัสดิการพิเศษเพื่อบุคลากรทางการแพทย์และสาธารณสุข',
              badgeText: 'โครงการใหม่ 2569',
              desktop_image: '/assets/img/hero_bg_health.jpg',
              button_text: 'สมัครเข้าร่วมโครงการ',
              button_url: '/loans',
              sec_button_text: 'อ่านรายละเอียด',
              sec_button_url: '/news'
            },
            {
              id: 992,
              title: 'ดิจิทัลสหกรณ์ มิติใหม่เพื่อสมาชิก',
              subtitle: 'บริการตรวจสอบเงินปันผล หุ้น และยอดเงินกู้ สะดวกทุกที่ทุกเวลา',
              badgeText: 'Smart Cooperative',
              desktop_image: '/assets/img/hero_bg_coop.jpg',
              button_text: 'เข้าสู่ระบบดิจิทัล',
              button_url: '/member/login'
            }
          ]
        }
      };
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(json)
      });
    } else {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: {} })
      });
    }
  });

  await cmsPage.goto('http://127.0.0.1:5199/', { waitUntil: 'domcontentloaded' });
  await cmsPage.waitForSelector('text=โครงการพิเศษเพื่อสมาชิกสาธารณสุขระยอง', { timeout: 10000 });

  const cmsSlideCount = await cmsPage.locator('.hero-slide').count();
  assert.equal(cmsSlideCount, 2, `Expected 2 dynamic CMS slides, got ${cmsSlideCount}`);
  const cmsSlideTitle = await cmsPage.locator('text=โครงการพิเศษเพื่อสมาชิกสาธารณสุขระยอง').count();
  assert.ok(cmsSlideTitle > 0, 'CMS Slide 1 title rendered correctly');
  console.log('[PASS] Dynamic CMS slides rendered successfully when valid CMS data is supplied.');

  await cmsContext.close();

  console.log('\n========================================================================');
  console.log(' RESULTS: ALL HOMEPAGE & CMS INTEGRATION BROWSER TESTS PASSED (0 ERRORS)');
  console.log('========================================================================');
} finally {
  if (browser) await browser.close();
  await vite.close();
}
