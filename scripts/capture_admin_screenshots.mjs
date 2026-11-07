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
    { kind: 'popups', name: 'PopupManager' },
    { kind: 'dividendImport', name: 'DividendImport' },
    { kind: 'membersDashboard', name: 'MembersDashboard' },
    { kind: 'members', name: 'MembersDirectory' },
    { kind: 'memberDetail', name: 'MemberDetail' },
    { kind: 'membersHealth', name: 'MembersHealth' }
  ];

  for (const pageItem of pagesToCapture) {
    console.log(`\n--- Capturing [${pageItem.name}] ---`);

    for (const vp of viewports) {
      const page = await browser.newPage({ viewport: { width: vp.width, height: vp.height }, reducedMotion: 'reduce' });

      // Mock essential API responses
      await page.route('**/api/**', (route) => {
        const url = new URL(route.request().url());

        if (url.pathname === '/api/admin/members/dashboard') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              members: { total: 2508, active: 2490, suspended: 12, resigned: 6, no_account: 350 },
              years: [
                { year: 2568, records: 2480, total_income: '12500000.00', total_deductions: '1200000.00', net_total: '11300000.00', updated_at: '2026-10-15 14:30:00' },
                { year: 2567, records: 2375, total_income: '11800000.00', total_deductions: '1150000.00', net_total: '10650000.00', updated_at: '2025-10-12 11:20:00' }
              ],
              runs: [
                { id: 12, created_at: '2026-10-15 14:30:00', source_name: 'dividend_2568_final.xlsx', year: 2568, record_count: 2480, created_count: 105, updated_count: 2375, unchanged_count: 0, net_total: '11300000.00', actor: 'เภสัชกร ทอม' }
              ],
              changes: [
                { id: 45, member_id: 1, member_no: '00004', member_name: 'ทอxx วิศิxxx', action: 'profile_update', reason: 'เปลี่ยนเบอร์โทรศัพท์ตามคำขอสมาชิก', created_at: '2026-11-01 09:15:00', actor: 'เจ้าหน้าที่ สหกรณ์' }
              ],
              warnings: []
            })
          });
        }

        if (url.pathname === '/api/admin/members/health') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              health: {
                ready: true,
                missing: [],
                extensions: { pdo_mysql: true, mbstring: true, zip: true, simplexml: true },
                member_admin_role: true
              }
            })
          });
        }

        if (url.pathname.match(/\/api\/admin\/members\/\d+$/)) {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              member: {
                id: 1,
                member_no: '00004',
                user_id: 2,
                prefix: 'ภก.',
                first_name: 'ทอม',
                last_name: 'วิศิษฏ์',
                name_pdpa: 'ภก.ทอxx วิศิxxx',
                id_card: '3219900100959',
                id_card_pdpa: '3-2199-00100-95-9',
                department: 'กลุ่มงานเภสัชกรรม รพ.ระยอง',
                position: 'เภสัชกรชำนาญการพิเศษ',
                phone: '081-xxx-xxxx',
                email: 'pharmacistxxx@rayongcoop.com',
                address: 'รพ.ระยอง อ.เมือง จ.ระยอง',
                status: 'active',
                join_date: '2018-05-10'
              },
              account: {
                id: 2,
                name_pdpa: 'ทอxx วิศิxxx',
                username_pdpa: '3-2199-00100-95-9',
                status: 'active',
                created_at: '2024-01-15 10:00:00',
                updated_at: '2026-10-15 14:30:00'
              },
              duplicates: { member_no: 0, id_card: 0 },
              dividends: [
                {
                  record: {
                    year: 2568,
                    total_income: 45000,
                    total_deductions: 5000,
                    net: 40000,
                    dividend_rate: 5.25,
                    refund_rate: 12.5,
                    income: { 'เงินปันผลตามหุ้น': 35000, 'เงินเฉลี่ยคืนเงินกู้': 10000 },
                    deductions: { 'หักชำระหนี้พิเศษ': 3000, 'หักเข้าบัญชีเงินฝากออมทรัพย์': 2000 }
                  },
                  updated_at: '2026-10-15 14:30:00'
                },
                {
                  record: {
                    year: 2567,
                    total_income: 42000,
                    total_deductions: 4500,
                    net: 37500,
                    dividend_rate: 5.0,
                    refund_rate: 12.0,
                    income: { 'เงินปันผลตามหุ้น': 33000, 'เงินเฉลี่ยคืนเงินกู้': 9000 },
                    deductions: { 'หักชำระหนี้พิเศษ': 2500, 'หักเข้าบัญชีเงินฝากออมทรัพย์': 2000 }
                  },
                  updated_at: '2025-10-12 11:20:00'
                }
              ],
              history: [
                {
                  id: 101,
                  action: 'profile_update',
                  reason: 'ปรับปรุงเบอร์โทรศัพท์ติดต่อและอีเมลตามคำขอ',
                  actor: 'เจ้าหน้าที่ทะเบียน',
                  created_at: '2026-11-01 09:15:00',
                  before_json: '{"phone":"081-111-1111","email":"old@rayongcoop.com"}',
                  after_json: '{"phone":"081-999-9999","email":"pharmacistxxx@rayongcoop.com"}'
                }
              ],
              warnings: []
            })
          });
        }

        if (url.pathname === '/api/admin/members') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              items: [
                { id: 1, member_no: '00004', name: 'ทอม วิศิษฏ์', name_pdpa: 'ทอxx วิศิxxx', department: 'กลุ่มงานเภสัชกรรม รพ.ระยอง', status: 'active', has_account: 1, id_card_pdpa: '3-2199-00100-95-9', record_count: 2 },
                { id: 2, member_no: '00006', name: 'สมศรี ใจดี', name_pdpa: 'สมxx ใจxx', department: 'กลุ่มงานการเงิน', status: 'active', has_account: 1, id_card_pdpa: '3-2099-00034-15-8', record_count: 2 },
                { id: 3, member_no: '00015', name: 'ประสิทธิ์ มั่นคง', name_pdpa: 'ประxxxx มั่นxx', department: 'รพ.สต.บ้านค่าย', status: 'active', has_account: 0, id_card_pdpa: '3-2199-00123-39-8', record_count: 1 },
                { id: 4, member_no: '00042', name: 'กาญจนา ศรีสุข', name_pdpa: 'กาญxxxx ศรีxx', department: 'สสจ.ระยอง', status: 'suspended', has_account: 1, id_card_pdpa: '3-2101-00335-99-6', record_count: 2 }
              ],
              total: 2508,
              page: 1,
              limit: 25,
              available_years: [2568, 2567],
              warnings: []
            })
          });
        }
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
        if (url.pathname === '/api/admin/popups') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              data: {
                items: [
                  {
                    id: 1,
                    title: 'เปิดรับสมัครสมาชิกใหม่ ประจำปี 2568',
                    subtitle: 'สิทธิประโยชน์เงินกู้และเงินออมดอกเบี้ยพิเศษ',
                    banner_image: '/assets/img/popup_dividend.jpg',
                    layout_mode: 'banner_only',
                    button_text: 'ดูรายละเอียด',
                    button_url: '/services',
                    button_target: '_self',
                    priority_order: 1,
                    status: 'active'
                  },
                  {
                    id: 2,
                    title: 'โครงการออมสุขสร้างอนาคต น้องออมสุขชวนออม',
                    subtitle: 'ฝากประจำดอกเบี้ยสูงเพื่อสมาชิกสหกรณ์',
                    mascot_image: '/assets/img/logo.webp',
                    layout_mode: 'mascot_only',
                    button_text: 'อ่านรายละเอียด',
                    button_url: '/deposits',
                    button_target: '_self',
                    priority_order: 2,
                    status: 'active'
                  }
                ]
              }
            })
          });
        }
        if (url.pathname === '/api/admin/system/health') {
          return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ status: 'healthy', database: 'connected', version: '3.1.0' })
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

      // Assert exactly one Topbar and one Sidebar
      const topbarCount = await page.locator('.acc-topbar').count();
      const sidebarCount = await page.locator('.acc-sidebar').count();
      assert.strictEqual(topbarCount, 1, `Expected exactly 1 .acc-topbar on ${pageItem.name}, found ${topbarCount}`);
      assert.strictEqual(sidebarCount, 1, `Expected exactly 1 .acc-sidebar on ${pageItem.name}, found ${sidebarCount}`);

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
