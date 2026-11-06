import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const mode = process.argv.includes('--after') ? 'after' : 'before';
const outDir = path.resolve(`storage/screenshots/admin-transformation/${mode}`);
await fs.mkdir(outDir, { recursive: true });

console.log('========================================================================');
console.log(` CAPTURING ADMIN SCREENSHOTS [MODE: ${mode.toUpperCase()}]`);
console.log('========================================================================');

const port = mode === 'after' ? 5198 : 5195;

const plugin = {
  name: 'admin-screenshot-fixture',
  enforce: 'pre',
  load(id) {
    const rel = path.relative(process.cwd(), id).replaceAll('\\', '/');
    if (rel === 'src/context/AuthContext.jsx') {
      return `
        export const useAuth = () => ({
          user: { id: 900, name: "เภสัชกร ทอม (ผู้ดูแลระบบ)", role: "super_admin", role_name: "Super Administrator", permissions: [] },
          isLoggedIn: true,
          sessionStatus: "online",
          can: () => true,
          logout: async () => {},
          refreshSession: async () => {},
          setShowAuthModal: () => {}
        });
      `;
    }
    if (rel === 'src/context/ToastContext.jsx') {
      return `
        export const useToast = () => ({
          toast: { success: () => {}, error: () => {}, info: () => {}, warning: () => {} },
          confirmDialog: async () => false
        });
      `;
    }
  },
  configureServer(vite) {
    vite.middlewares.use('/__admin_fixture', async (req, res) => {
      res.setHeader('Content-Type', 'text/html');
      res.end(await vite.transformIndexHtml('/__admin_fixture', `
        <!DOCTYPE html>
        <html lang="th">
          <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
            <title>Admin Presentation Capture</title>
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
  cacheDir: `storage/tmp/vite-admin-screenshots-${mode}`,
  plugins: [plugin, react()],
  resolve: { alias: { 'react-router-dom': 'react-router' } },
  server: { host: '127.0.0.1', port, strictPort: true },
  optimizeDeps: {
    noDiscovery: true,
    include: ['react', 'react-dom/client', 'react/jsx-runtime', 'react/jsx-dev-runtime', 'react-router', 'lucide-react', 'quill', 'dompurify', 'canvas-confetti', 'clsx', 'sweetalert2']
  }
});

let browser;
const overflowReports = [];

try {
  await server.listen();
  console.log(` [VITE] Capture server listening on http://127.0.0.1:${port}`);

  const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  browser = await chromium.launch({
    headless: true,
    executablePath: process.env.PLAYWRIGHT_EXECUTABLE || chromePath
  });

  const viewports = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'tablet', width: 1024, height: 768 },
    { name: 'mobile', width: 390, height: 844 }
  ];

  const pagesToCapture = [
    { kind: 'dashboard', name: 'Dashboard' },
    { kind: 'workspace', name: 'Workspace' },
    { kind: 'dividendImport', name: 'DividendImport' }
  ];

  for (const pageItem of pagesToCapture) {
    console.log(`\n--- Capturing [${pageItem.name}] ---`);

    for (const vp of viewports) {
      const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height }, reducedMotion: 'reduce' });

      // Mock essential API responses
      await page.route('**/api/**', (route) => {
        const url = new URL(route.request().url());
        if (url.pathname === '/api/admin/dashboard/summary') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              kpis: [
                { key: 'total_users', title: 'ผู้ใช้งานทั้งหมด', value: 2510, unit: 'บัญชี', subtitle: 'ฐานข้อมูลจริง', variant: 'primary' },
                { key: 'active_staff', title: 'เจ้าหน้าที่ปฏิบัติงาน', value: 3, unit: 'คน', subtitle: 'มีสิทธิ์เข้าระบบ', variant: 'info' },
                { key: 'active_members', title: 'สมาชิกสหกรณ์', value: 2508, unit: 'คน', subtitle: 'สมาชิกที่ใช้งาน', variant: 'success' },
                { key: 'pending_approvals', title: 'คำขอรออนุมัติ', value: 0, unit: 'รายการ', subtitle: 'Maker-Checker Gate', variant: 'neutral' }
              ]
            })
          });
        }
        if (url.pathname === '/api/admin/dashboard/trends') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true, charts: {} })
          });
        }
        if (url.pathname === '/api/admin/operations') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true, overview: { total_tasks: 0, pending: 0, completed: 0 } })
          });
        }
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: [] })
        });
      });

      const url = `http://127.0.0.1:${port}/__admin_fixture?kind=${pageItem.kind}&theme=light`;
      await page.goto(url, { waitUntil: 'networkidle' });
      await page.waitForTimeout(600);

      // Check for horizontal overflow
      const hasOverflow = await page.evaluate(() => {
        const doc = document.documentElement;
        return doc.scrollWidth > doc.clientWidth;
      });

      if (hasOverflow) {
        console.log(` [WARN] Horizontal overflow detected on ${pageItem.name} [${vp.name}: ${vp.width}px]`);
        overflowReports.push({ page: pageItem.name, viewport: vp.name, width: vp.width });
      }

      const filename = `${pageItem.kind}-${vp.name}-${vp.width}.png`;
      const filePath = path.join(outDir, filename);
      await page.screenshot({ path: filePath, fullPage: true });
      console.log(` [SAVED] ${filename} (${vp.width}x${vp.height})`);

      await page.close();
    }
  }

  console.log('\n========================================================================');
  console.log(` CAPTURE COMPLETE: Output stored in storage/screenshots/admin-transformation/${mode}`);
  if (overflowReports.length > 0) {
    console.log(` ATTENTION: ${overflowReports.length} viewports exhibited horizontal overflow.`);
  } else {
    console.log(' CLEAN: Zero horizontal overflow detected across all captured viewports.');
  }
  console.log('========================================================================');

} finally {
  if (browser) await browser.close();
  await server.close();
}
