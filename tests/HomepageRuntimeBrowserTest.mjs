import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import { chromium } from 'playwright';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');

console.log('========================================================================');
console.log(' RYCOOPWEB — HOMEPAGE & ROUTE RUNTIME BROWSER REGRESSION TEST');
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
  // Launch Playwright (try chromium, fallback to msedge if needed)
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
    const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height } });
    page.setDefaultTimeout(30000);

    const pageErrors = [];
    page.on('pageerror', (err) => {
      console.error(`[PAGE ERROR ${vp.name}] ${err.message}`);
      pageErrors.push(err.message);
    });

    // 1. Test Homepage
    console.log(`[TEST 1] Navigating to Homepage (/) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    // Verify no uncaught ReferenceError or page crash
    assert.equal(pageErrors.length, 0, `Homepage had uncaught JS errors: ${pageErrors.join(', ')}`);

    // Verify Error Boundary did not catch a crash
    const errorBoundary = await page.locator('text=Something went wrong').count();
    assert.equal(errorBoundary, 0, 'Homepage showed an Error Boundary!');

    // Verify Hero section / Hero slider rendered
    const heroSlider = await page.locator('.home-hero-grid, .hero-news-slideshow, .hero-slider-wrapper, .hero-slider, [aria-roledescription="carousel"], .hero-section').count();
    assert.ok(heroSlider > 0, 'Hero section / slider container rendered');
    console.log(`[PASS] Homepage and Hero Slider rendered cleanly without crash [${vp.name}].`);

    // Verify Quick Cards (specifically Calendar icon card 'ปฏิทินยื่นกู้')
    const calendarCard = await page.locator('text=ปฏิทินยื่นกู้').count();
    assert.ok(calendarCard > 0, "'ปฏิทินยื่นกู้' quick card rendered");
    console.log(`[PASS] Quick card 'ปฏิทินยื่นกู้' (with Calendar icon) rendered successfully [${vp.name}].`);

    // 2. Test Navigation to Loan Schedule (/loan-schedule)
    console.log(`[TEST 2] Navigating to Loan Schedule (/loan-schedule) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/loan-schedule', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    assert.equal(pageErrors.length, 0, `Loan Schedule had uncaught JS errors: ${pageErrors.join(', ')}`);
    const scheduleHeader = await page.getByRole('heading', { level: 1 }).count();
    assert.ok(scheduleHeader > 0, 'Loan schedule page header rendered');
    console.log(`[PASS] Loan Schedule route rendered cleanly [${vp.name}].`);

    // 3. Test Navigation to Documents (/documents)
    console.log(`[TEST 3] Navigating to Documents (/documents) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/documents', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    assert.equal(pageErrors.length, 0, `Documents had uncaught JS errors: ${pageErrors.join(', ')}`);
    const docsHeader = await page.getByRole('heading', { level: 1 }).count();
    assert.ok(docsHeader > 0, 'Documents page header rendered');
    console.log(`[PASS] Documents route rendered cleanly [${vp.name}].`);

    // 4. Test Navigation / Member Login
    console.log(`[TEST 4] Navigating to Member Login Page (/member/login) [${vp.name}]...`);
    await page.goto('http://127.0.0.1:5199/member/login', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    assert.equal(pageErrors.length, 0, `Member Login page had uncaught JS errors: ${pageErrors.join(', ')}`);
    const loginForm = await page.locator('form, input[type="password"], button[type="submit"]').count();
    assert.ok(loginForm > 0, 'Member login form rendered');
    console.log(`[PASS] Member Login route rendered cleanly [${vp.name}].`);

    await page.close();
  }

  console.log('\n========================================================================');
  console.log(' RESULTS: ALL BROWSER RUNTIME REGRESSION TESTS PASSED (0 ERRORS)');
  console.log('========================================================================');
} finally {
  if (browser) await browser.close();
  await vite.close();
}

