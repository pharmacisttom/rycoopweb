import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const require = createRequire(path.resolve(process.env.PLAYWRIGHT_PACKAGE_ROOT || '.', 'package.json'));
const { chromium } = require('playwright');

const port = 5208;
const qaOutputDir = 'storage/tmp/admin-platform-qa';
const artifactDir = 'C:/Users/ACER/.gemini/antigravity/brain/6f718d35-ff7b-472d-bc44-a9827f67fee8';

await fs.mkdir(qaOutputDir, { recursive: true });
try {
  await fs.mkdir(artifactDir, { recursive: true });
} catch {}

const plugin = {
  name: 'admin-smoke-test-plugin',
  enforce: 'pre',
  load(id) {
    const relative = path.relative(process.cwd(), id).replaceAll('\\', '/');
    if (relative === 'src/context/AuthContext.jsx') {
      return `export const useAuth = () => ({
        user: { id: 999, name: "Super Administrator", role: "super_admin", permissions: ["*"] },
        isLoggedIn: true,
        sessionStatus: "online",
        can: () => true,
        logout: async () => {},
        refreshSession: async () => {},
        setShowAuthModal: () => {}
      });`;
    }
    if (relative === 'src/context/ToastContext.jsx') {
      return `export const useToast = () => ({
        toast: { success: () => {}, error: () => {}, info: () => {}, warning: () => {} },
        confirmDialog: async () => false
      });`;
    }
  },
  configureServer(vite) {
    vite.middlewares.use('/__admin_fixture', async (request, response) => {
      response.setHeader('Content-Type', 'text/html');
      response.end(await vite.transformIndexHtml('/__admin_fixture', `
        <!DOCTYPE html>
        <html lang="th">
          <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width,initial-scale=1" />
            <title>Admin Platform Smoke Test</title>
          </head>
          <body>
            <div id="root"></div>
            <script type="module" src="/tests/fixtures/AdminUI.jsx"></script>
          </body>
        </html>
      `));
    });
  }
};

const server = await createServer({
  configFile: false,
  cacheDir: 'storage/tmp/vite-admin-smoke-test',
  plugins: [plugin, react()],
  resolve: { alias: { 'react-router-dom': 'react-router' } },
  server: {
    host: '127.0.0.1',
    port,
    strictPort: true,
    watch: { ignored: ['**/public/app/**', '**/storage/tmp/**'] }
  },
  optimizeDeps: {
    noDiscovery: true,
    include: ['react', 'react-dom/client', 'react/jsx-runtime', 'react/jsx-dev-runtime', 'react-router', 'lucide-react', 'quill', 'dompurify', 'canvas-confetti', 'clsx']
  }
});

let browser;
const pageErrors = [];

try {
  await server.listen();
  console.log(`Vite test server listening on http://127.0.0.1:${port}`);

  const launchOptions = process.env.PLAYWRIGHT_EXECUTABLE
    ? { executablePath: process.env.PLAYWRIGHT_EXECUTABLE }
    : { channel: 'chrome' };
  browser = await chromium.launch(launchOptions);

  const page = await browser.newPage({ reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);

  page.on('pageerror', (err) => {
    console.error('Browser Page Error:', err.message);
    pageErrors.push(err.message);
  });

  const json = (route, body, status = 200) =>
    route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });

  await page.route('**/csrf-token', (route) => json(route, { token: 'smoke-csrf-token' }));

  // Mock Admin APIs with dual-envelope payloads
  await page.route('**/api/**', (route) => {
    const url = new URL(route.request().url());
    const pathname = url.pathname;

    if (pathname === '/api/admin/system/health') {
      const payload = {
        overall_status: 'healthy',
        overall_label: 'พร้อมใช้งาน (Healthy)',
        summary_text: 'ระบบทำงานปกติ บริการหลักพร้อมใช้งาน 100%',
        resources: {
          php_memory_usage_mb: 28.5,
          php_memory_limit: '256M',
          disk_free_gb: 42.8,
          disk_used_percent: 45.2,
          cpu_load: '0.35, 0.42, 0.38'
        },
        services: [
          { name: 'PHP-FPM Runtime', status: 'healthy', detail: 'PHP 8.3.18 FPM Active' },
          { name: 'MySQL Database', status: 'healthy', detail: 'Connected to rycoopweb_test' },
          { name: 'Storage / Backups', status: 'healthy', detail: 'storage/backups writable (0600)' }
        ],
        scheduled_jobs: [
          { job: 'Daily Security Audit Rollup', schedule: '02:00 daily', status: 'active', command: 'php bin/console cron:audit' },
          { job: 'Database Backup & Checksum', schedule: '03:00 daily', status: 'active', command: 'php bin/console cron:backup' }
        ],
        recent_errors: []
      };
      return json(route, { success: true, correlation_id: 'test-health-corr', data: payload, ...payload });
    }

    if (pathname === '/api/admin/backups') {
      const payload = {
        freshness_status: 'healthy',
        phase_mode: 'Phase 1: Read-Only Monitoring & Checksum Verification',
        total_backups: 14,
        total_size_mb: 182.4,
        latest_backup_at: '2026-11-08 03:00:00',
        freshness_hours: 5.2,
        verified_backups: 14,
        retention_policy: 'เก็บรักษาใน storage/backups นอก public webroot สิทธิ์ไฟล์ 0600',
        recent_history: [
          { filename: 'backup_20261108_0300.sql.gz', category_label: 'Full DB', size_kb: 13500, created_at: '2026-11-08 03:00:00', integrity_verified: true }
        ],
        categories_status: [
          { category: 'Database (MySQL)', status: 'available' }
        ]
      };
      return json(route, { success: true, correlation_id: 'test-bkp-corr', data: payload, ...payload });
    }

    if (pathname === '/api/admin/security/overview') {
      const payload = {
        health_score: { score: 95, max_score: 100, rules: [] },
        metrics: { failed_logins_24h: 2, failed_logins_7d: 12, mfa_failures_7d: 1, session_revocations_30d: 0 },
        disclaimer: 'คำนวณจากกฎการตั้งค่าความปลอดภัยจริง'
      };
      return json(route, { success: true, correlation_id: 'test-sec-corr', data: payload, ...payload });
    }

    if (pathname === '/api/admin/reports/catalog') {
      const payload = {
        catalog: [
          { id: 'REP-FIN-01', title: 'รายงานสถิติทางการเงินและอัตราดอกเบี้ย', description: 'สถิติรายเดือน ทุนเรือนหุ้น เงินฝาก เงินกู้', category: 'การเงิน (Finance)', authorized: true },
          { id: 'REP-DIV-02', title: 'รายงานสรุปการจัดสรรเงินปันผลประจำปี', description: 'ประวัติการนำเข้าเงินปันผลรายปี', category: 'สมาชิกและเงินปันผล (Dividends)', authorized: true },
          { id: 'REP-SEC-03', title: 'รายงานการตรวจสอบความมั่นคงปลอดภัย', description: 'บันทึก Audit Logs และ Security Events', category: 'ความปลอดภัย (Security)', authorized: true }
        ],
        permissions: { can_finance: true, can_audit: true, can_members: true }
      };
      return json(route, { success: true, correlation_id: 'test-rep-corr', data: payload, ...payload });
    }

    if (pathname === '/api/admin/operations/overview') {
      const payload = {
        summary: { profile_requests: 0, open_complaints: 0, news_drafts: 0, content_drafts: 0, pending_approvals: 0 },
        queues: { member_profile_requests: [], open_complaints: [], news_drafts: [], public_content_drafts: [], approval_requests: [] }
      };
      return json(route, { success: true, correlation_id: 'test-ops-corr', data: payload, ...payload });
    }

    return json(route, { success: true, data: {} });
  });

  const saveScreenshot = async (name) => {
    const tmpPath = path.join(qaOutputDir, name);
    await page.screenshot({ path: tmpPath, fullPage: true });
    try {
      const artPath = path.join(artifactDir, name);
      await fs.copyFile(tmpPath, artPath);
    } catch {}
    console.log(`Saved screenshot: ${name}`);
  };

  // 1. Test /admin/health on Desktop (1440px)
  console.log('\n--- Test 1: /admin/health Desktop (1440px) ---');
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`http://127.0.0.1:${port}/__admin_fixture?kind=health`);
  await page.locator('.acc-shell').waitFor();

  // Verify KPI cards rendered without crash
  const healthKpiLabel = await page.locator('.acc-kpi-label').first().innerText();
  assert.equal(healthKpiLabel, 'สถานะภาพรวมของแอปพลิเคชัน');
  console.log('PASS: Health KPI label rendered correctly');

  const diskSpaceText = await page.getByText('พื้นที่ดิสก์ว่าง (Disk Free Space)').isVisible();
  assert.equal(diskSpaceText, true, 'Disk space card must be visible');
  console.log('PASS: Disk space KPI card is visible');

  const coreServicesHeading = await page.getByText('สถานะบริการหลัก (Core Service Readiness)').isVisible();
  assert.equal(coreServicesHeading, true, 'Core services table heading must be visible');
  console.log('PASS: Core services table is visible');

  await saveScreenshot('01_health_desktop_1440.png');

  // 2. Test /admin/health on Mobile (390px)
  console.log('\n--- Test 2: /admin/health Mobile (390px) ---');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`http://127.0.0.1:${port}/__admin_fixture?kind=health`);
  await page.locator('.acc-shell').waitFor();
  await saveScreenshot('02_health_mobile_390.png');
  console.log('PASS: Health dashboard rendered cleanly on mobile 390px');

  // 3. Test /admin/backups on Desktop (1440px)
  console.log('\n--- Test 3: /admin/backups Desktop (1440px) ---');
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`http://127.0.0.1:${port}/__admin_fixture?kind=backups`);
  await page.locator('.acc-shell').waitFor();
  const backupFreshness = page.getByText('สถานะความสดใหม่ของข้อมูลสำรอง');
  await backupFreshness.waitFor();
  assert.equal(await backupFreshness.isVisible(), true, 'Backup freshness KPI card must be visible');
  await saveScreenshot('03_backups_desktop_1440.png');
  console.log('PASS: Backups monitoring dashboard rendered cleanly');

  // 4. Test /admin/security on Desktop (1440px)
  console.log('\n--- Test 4: /admin/security Desktop (1440px) ---');
  await page.goto(`http://127.0.0.1:${port}/__admin_fixture?kind=security`);
  await page.locator('.acc-shell').waitFor();
  const secScore = page.getByText('คะแนนสุขภาพความปลอดภัย (Rule-Based Score)');
  await secScore.waitFor();
  assert.equal(await secScore.isVisible(), true, 'Security score KPI card must be visible');
  await saveScreenshot('04_security_desktop_1440.png');
  console.log('PASS: Security overview rendered cleanly');

  // 5. Test /admin/reports on Desktop (1440px)
  console.log('\n--- Test 5: /admin/reports Desktop (1440px) ---');
  await page.goto(`http://127.0.0.1:${port}/__admin_fixture?kind=reports`);
  await page.locator('.acc-shell').waitFor();
  const repTitle = page.getByText('ศูนย์รายงานผู้บริหาร การเงิน การตรวจสอบ และความมั่นคงปลอดภัย');
  await repTitle.waitFor();
  assert.equal(await repTitle.isVisible(), true, 'Reports center title must be visible');
  await saveScreenshot('05_reports_desktop_1440.png');
  console.log('PASS: Reports catalog rendered cleanly with authorized reports');

  // Check no unhandled errors thrown during the entire browser session
  assert.equal(pageErrors.length, 0, `Expected 0 page errors, found: ${pageErrors.join(', ')}`);
  console.log('\nALL 5 BROWSER SMOKE CHECKS PASSED WITH ZERO PAGE ERRORS!\n');

} finally {
  if (browser) await browser.close();
  await server.close();
}
