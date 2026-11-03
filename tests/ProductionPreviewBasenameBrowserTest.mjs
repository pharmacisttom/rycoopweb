import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { createServer as createViteServer } from 'vite';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const appDistDir = path.join(rootDir, 'public', 'app');
const artifactDir = 'C:\\Users\\ACER\\.gemini\\antigravity\\brain\\6f718d35-ff7b-472d-bc44-a9827f67fee8';

const MIME_TYPES = {
  '.html': 'text/html',
  '.js': 'application/javascript',
  '.css': 'text/css',
  '.json': 'application/json',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2'
};

// 1. Create Static Production Preview Server simulating Nginx with root at public/
function startProdPreviewServer(port = 5210) {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const url = new URL(req.url, `http://127.0.0.1:${port}`);
      let pathname = decodeURIComponent(url.pathname);

      // Blocked / protected API simulation (matching Nginx rule: unauthorised requests to protected APIs)
      if (pathname.startsWith('/api/admin/') || pathname.startsWith('/api/member/')) {
        res.writeHead(403, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, message: 'Forbidden - Protected API' }));
        return;
      }

      // Public API mock for home data if requested
      if (pathname.startsWith('/api/public/home')) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true, data: {} }));
        return;
      }

      // If request is under /app/assets/
      if (pathname.startsWith('/app/assets/')) {
        const relPath = pathname.replace('/app/assets/', '');
        const filePath = path.join(appDistDir, 'assets', relPath);
        if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
          const ext = path.extname(filePath);
          res.writeHead(200, {
            'Content-Type': MIME_TYPES[ext] || 'application/octet-stream',
            'Cache-Control': 'public, max-age=31536000'
          });
          fs.createReadStream(filePath).pipe(res);
          return;
        }
      }

      // If request is under /app or /app/*, serve public/app/index.html (simulating Nginx try_files $uri /app/index.html)
      if (pathname === '/app' || pathname === '/app/' || pathname.startsWith('/app/')) {
        const indexPath = path.join(appDistDir, 'index.html');
        if (fs.existsSync(indexPath)) {
          res.writeHead(200, {
            'Content-Type': 'text/html; charset=utf-8',
            'Cache-Control': 'no-store, no-cache, must-revalidate'
          });
          fs.createReadStream(indexPath).pipe(res);
          return;
        }
      }

      // Otherwise 404
      res.writeHead(404, { 'Content-Type': 'text/plain' });
      res.end('Not Found');
    });

    server.listen(port, '127.0.0.1', () => {
      resolve(server);
    });
    server.on('error', reject);
  });
}

async function runTests() {
  console.log('========================================================================');
  console.log(' RYCOOPWEB — PRODUCTION PREVIEW BASENAME (/app/) & DEV ROUTING TEST');
  console.log('========================================================================');

  // Verify index.html in public/app/ exists and references /app/assets/
  const prodIndexHtmlPath = path.join(appDistDir, 'index.html');
  assert.ok(fs.existsSync(prodIndexHtmlPath), 'public/app/index.html must exist from build');
  const prodIndexHtml = fs.readFileSync(prodIndexHtmlPath, 'utf8');
  assert.ok(prodIndexHtml.includes('/app/assets/'), 'public/app/index.html must link to /app/assets/');
  console.log('[PASS] Verified public/app/index.html uses /app/assets/ base prefix.');

  const prodPort = 5210;
  const prodServer = await startProdPreviewServer(prodPort);
  console.log(`[PASS] Production preview server running on http://127.0.0.1:${prodPort}/app/`);

  // Start Vite Dev Server on port 5211 to verify dev routing at /
  const devPort = 5211;
  const viteDev = await createViteServer({
    root: rootDir,
    server: { port: devPort, host: '127.0.0.1' },
    logLevel: 'error'
  });
  await viteDev.listen();
  console.log(`[PASS] Vite dev server running on http://127.0.0.1:${devPort}/`);

  let browser;
  try {
    try {
      browser = await chromium.launch({ headless: true });
    } catch (e) {
      console.log('[DIAGNOSTIC] Standard chromium launch fallback to msedge channel...');
      browser = await chromium.launch({ channel: 'msedge', headless: true });
    }
    console.log('[PASS] Browser launched successfully.');

    // ──────────────────────────────────────────────────────────────────────────
    // PART 1: PRODUCTION PREVIEW AT /app/ (Desktop & Mobile)
    // ──────────────────────────────────────────────────────────────────────────
    const viewports = [
      { name: 'Desktop', width: 1280, height: 800 },
      { name: 'Mobile', width: 390, height: 844 },
    ];

    for (const vp of viewports) {
      console.log(`\n--- [PROD PREVIEW /app/] Testing Viewport: ${vp.name} (${vp.width}x${vp.height}) ---`);
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

      // Helper to assert no 404 or Global Error Boundary
      const assertCleanRoute = async (routeName) => {
        assert.equal(pageErrors.length, 0, `Uncaught JS errors on ${routeName}: ${pageErrors.join(', ')}`);
        
        // Assert no Internal 404 page
        const internal404 = await page.locator('text=ไม่พบหน้าที่คุณต้องการ').count();
        assert.equal(internal404, 0, `Internal 404 detected on valid route ${routeName}!`);

        // Assert no Global Error Boundary
        const errorBoundary = await page.locator('text=เกิดข้อผิดพลาดในการโหลดหน้าเว็บ').count();
        assert.equal(errorBoundary, 0, `Global Error Boundary caught crash on ${routeName}!`);
      };

      // 1. Direct Entry to /app/
      console.log(`[PROD 1] Navigating to http://127.0.0.1:${prodPort}/app/ [${vp.name}]...`);
      await page.goto(`http://127.0.0.1:${prodPort}/app/`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(1000);
      await assertCleanRoute('Homepage (/app/)');

      // Verify HeroSlider rendered (NOT 404)
      const heroSlider = page.locator('.hero-slider-wrapper');
      assert.ok(await heroSlider.count() > 0, '.hero-slider-wrapper must exist on /app/');
      assert.ok(await heroSlider.isVisible(), '.hero-slider-wrapper must be visible on /app/');
      console.log(`[PASS] HeroSlider visible on /app/ [${vp.name}].`);

      // Verify 4 Slides
      const slideCount = await page.locator('.hero-slide').count();
      assert.equal(slideCount, 4, `Expected 4 default slides on /app/, got ${slideCount}`);
      console.log(`[PASS] 4 default slides present on /app/ [${vp.name}].`);

      // Verify Quick Access Cards
      const quickCards = ['เข้าสู่ระบบสมาชิก', 'เงินกู้ของสหกรณ์', 'เงินฝาก', 'เอกสารดาวน์โหลด', 'ข่าวสารและประกาศ', 'ปฏิทินยื่นกู้'];
      for (const title of quickCards) {
        assert.ok(await page.locator(`text=${title}`).count() > 0, `Quick card ${title} must exist`);
      }
      console.log(`[PASS] All 6 Quick Access Cards rendered on /app/ [${vp.name}].`);

      // Verify Chairperson Leadership section
      assert.ok(await page.locator('text=สารจากประธานกรรมการสหกรณ์').count() > 0, 'Chairperson section missing');
      assert.ok(await page.locator('text=นางสาวอุษา อิศรางกูร ณ อยุธยา').count() > 0, 'Chairperson name missing');
      console.log(`[PASS] Leadership section rendered on /app/ [${vp.name}].`);

      // Save screenshot
      if (fs.existsSync(artifactDir)) {
        const screenshotPath = path.join(artifactDir, `uat-prod-preview-${vp.name.toLowerCase()}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: false });
        console.log(`[PASS] Saved Production Preview screenshot to ${screenshotPath}`);
      }

      // 2. Direct Entry to /app (without trailing slash)
      console.log(`[PROD 2] Navigating to http://127.0.0.1:${prodPort}/app [${vp.name}]...`);
      await page.goto(`http://127.0.0.1:${prodPort}/app`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      await assertCleanRoute('Homepage (/app without trailing slash)');
      assert.ok(await page.locator('.hero-slider-wrapper').count() > 0, 'HeroSlider must exist on /app');
      console.log(`[PASS] /app without trailing slash rendered without 404 [${vp.name}].`);

      // 3. Direct Entry to /app/loan-schedule
      console.log(`[PROD 3] Direct entry to http://127.0.0.1:${prodPort}/app/loan-schedule [${vp.name}]...`);
      await page.goto(`http://127.0.0.1:${prodPort}/app/loan-schedule`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      await assertCleanRoute('/app/loan-schedule');
      const loanHeader = await page.getByRole('heading', { level: 1 }).count();
      assert.ok(loanHeader > 0, 'Loan schedule heading must exist');
      console.log(`[PASS] /app/loan-schedule direct entry clean [${vp.name}].`);

      // 4. Direct Entry to /app/documents
      console.log(`[PROD 4] Direct entry to http://127.0.0.1:${prodPort}/app/documents [${vp.name}]...`);
      await page.goto(`http://127.0.0.1:${prodPort}/app/documents`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      await assertCleanRoute('/app/documents');
      const docsHeader = await page.getByRole('heading', { level: 1 }).count();
      assert.ok(docsHeader > 0, 'Documents heading must exist');
      console.log(`[PASS] /app/documents direct entry clean [${vp.name}].`);

      // 5. In-app navigation via Link & Refresh test
      console.log(`[PROD 5] Testing Link navigation and Refresh under /app/ [${vp.name}]...`);
      await page.goto(`http://127.0.0.1:${prodPort}/app/`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(600);

      // Find link to /loan-schedule or click Quick Access Card "ปฏิทินยื่นกู้"
      const scheduleCard = page.locator('text=ปฏิทินยื่นกู้').first();
      await scheduleCard.click();
      await page.waitForTimeout(800);
      assert.ok(page.url().includes('/app/loan-schedule'), `URL must include /app/loan-schedule, got ${page.url()}`);
      await assertCleanRoute('Navigated /app/loan-schedule');
      console.log(`[PASS] In-app navigation preserved /app/ prefix: ${page.url()} [${vp.name}].`);

      // Refresh /app/loan-schedule directly
      await page.reload({ waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      await assertCleanRoute('Refreshed /app/loan-schedule');
      console.log(`[PASS] Direct page refresh on /app/loan-schedule succeeded without 404 [${vp.name}].`);

      // 6. Member Portal Disabled Verification for Production Public-Only Deployment
      console.log(`[PROD 6] Verifying Member Portal is disabled in Production preview [${vp.name}]...`);
      await page.goto(`http://127.0.0.1:${prodPort}/app/eservice`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      const serviceUnavailableText = await page.locator('text=ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ').count();
      assert.ok(serviceUnavailableText > 0, 'Visiting /app/eservice must show ServiceUnavailablePage when member portal is disabled');
      console.log(`[PASS] /app/eservice renders ServiceUnavailablePage as expected [${vp.name}].`);

      await page.goto(`http://127.0.0.1:${prodPort}/app/member/dashboard`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      const dashboardUnavailable = await page.locator('text=ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ').count();
      assert.ok(dashboardUnavailable > 0, 'Visiting /app/member/dashboard must show ServiceUnavailablePage when portal disabled');
      console.log(`[PASS] /app/member/dashboard safely blocked with ServiceUnavailablePage [${vp.name}].`);

      // 7. Verify genuine 404 behavior for unknown paths
      console.log(`[PROD 7] Verifying genuine 404 for unknown route [${vp.name}]...`);
      await page.goto(`http://127.0.0.1:${prodPort}/app/non-existent-page-test-12345`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      const is404 = await page.locator('text=ไม่พบหน้าที่คุณต้องการ').count();
      assert.ok(is404 > 0, 'Genuine non-existent path must show 404 page');
      console.log(`[PASS] Genuine 404 rendered correctly for unknown paths [${vp.name}].`);

      await context.close();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PART 2: DEVELOPMENT ROUTING AT / (Port 5211)
    // ──────────────────────────────────────────────────────────────────────────
    console.log('\n--- [DEV ROUTING /] Testing Development Mode at / ---');
    const devContext = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    await devContext.addInitScript(() => {
      sessionStorage.setItem('coop_campaign_shown', 'true');
    });
    const devPage = await devContext.newPage();
    const devErrors = [];
    devPage.on('pageerror', (err) => devErrors.push(err.message));

    console.log(`[DEV 1] Navigating to http://127.0.0.1:${devPort}/...`);
    await devPage.goto(`http://127.0.0.1:${devPort}/`, { waitUntil: 'domcontentloaded' });
    await devPage.waitForTimeout(1000);
    assert.equal(devErrors.length, 0, `Dev errors: ${devErrors.join(', ')}`);
    assert.equal(await devPage.locator('text=ไม่พบหน้าที่คุณต้องการ').count(), 0, 'Dev root must not show 404');
    assert.ok(await devPage.locator('.hero-slider-wrapper').count() > 0, 'Dev root must render HeroSlider');
    console.log('[PASS] Development mode routing at / works cleanly.');

    console.log(`[DEV 2] Navigating to http://127.0.0.1:${devPort}/loan-schedule...`);
    await devPage.goto(`http://127.0.0.1:${devPort}/loan-schedule`, { waitUntil: 'domcontentloaded' });
    await devPage.waitForTimeout(800);
    assert.equal(devErrors.length, 0, `Dev errors: ${devErrors.join(', ')}`);
    assert.equal(await devPage.locator('text=ไม่พบหน้าที่คุณต้องการ').count(), 0, 'Dev /loan-schedule must not show 404');
    console.log('[PASS] Development mode routing at /loan-schedule works cleanly.');

    await devContext.close();

    console.log('\n========================================================================');
    console.log(' RESULTS: ALL PRODUCTION PREVIEW & DEV ROUTING TESTS PASSED (0 ERRORS)');
    console.log('========================================================================');
  } finally {
    if (browser) await browser.close();
    await viteDev.close();
    await new Promise((resolve) => prodServer.close(resolve));
  }
}

runTests().catch((err) => {
  console.error('[FATAL TEST ERROR]', err);
  process.exit(1);
});
