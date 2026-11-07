import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';
import { chromium } from 'playwright';

const outDir = 'storage/tmp/selective-transfer-qa';
await fs.mkdir(outDir, { recursive: true });

const plugin = {
  name: 'selective-transfer-ui-fixture',
  enforce: 'pre',
  load(id) {
    if (path.relative(process.cwd(), id).replaceAll('\\', '/') === 'src/context/AuthContext.jsx') {
      return 'export const useAuth = () => ({ user: { id: 900, name: "Admin Tester", role: "super_admin" }, can: () => true, isLoggedIn: true, sessionStatus: "online", logout: async () => {} });';
    }
  },
  configureServer(vite) {
    vite.middlewares.use('/__crud', async (req, res) => {
      res.setHeader('Content-Type', 'text/html');
      res.end(await vite.transformIndexHtml('/__crud', '<html lang="th"><meta name="viewport" content="width=device-width,initial-scale=1"><body><div id="root"></div><script type="module" src="/tests/fixtures/AdminUI.jsx"></script></body></html>'));
    });
  }
};

const server = await createServer({
  configFile: false,
  cacheDir: 'storage/tmp/vite-transfer-qa',
  plugins: [plugin, react()],
  resolve: { alias: { 'react-router-dom': 'react-router' } },
  server: { host: '127.0.0.1', port: 5204, strictPort: true, watch: { ignored: ['**/storage/tmp/**', '**/public/app/**'] } },
  optimizeDeps: {
    noDiscovery: true,
    include: ['react', 'react-dom/client', 'react/jsx-runtime', 'react/jsx-dev-runtime', 'react-router', 'lucide-react', 'quill', 'dompurify', 'canvas-confetti', 'clsx']
  }
});

let browser;
try {
  await server.listen();
  const chromePath = process.env.PLAYWRIGHT_EXECUTABLE || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  browser = await chromium.launch({ executablePath: chromePath, headless: true });
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  page.setDefaultTimeout(30000);
  page.on('dialog', d => d.accept());
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  const json = (route, body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });

  await page.route('**/csrf-token', route => json(route, { token: 'fixture-csrf' }));

  let transferredPayload = null;

  await page.route('**/api/**', route => {
    const req = route.request();
    const url = new URL(req.url());
    const pathname = url.pathname;

    if (pathname === '/api/admin/public-content/sections') {
      return json(route, { success: true, sections: { board: { title: 'คณะกรรมการ', updated_at: '2026-11-07 10:00:00' } } });
    }

    if (pathname === '/api/admin/board-terms') {
      return json(route, {
        success: true,
        data: [
          {
            id: 1,
            term_number: 38,
            term_name: 'ชุดที่ 38',
            year_start_be: 2567,
            year_end_be: 2568,
            status: 'published',
            is_current: true,
            director_count: 15,
            advisor_count: 3,
            auditor_count: 3,
            group_count: 3
          }
        ]
      });
    }

    if (pathname === '/api/admin/board-terms/1') {
      return json(route, {
        success: true,
        data: {
          term: { id: 1, term_number: 38, term_name: 'ชุดที่ 38', status: 'published', is_current: true },
          directors: [
            { id: 101, name: 'นางสาวอุษา อิศรางกูร ณ อยุธยา', position: 'ประธานกรรมการ', role_type: 'president', image_url: '/assets/img/committee/committee-01-president.webp', category: 'director', sort_order: 1, is_chairperson: true },
            { id: 102, name: 'นางณาทัศญา ทรัพย์อักษรศิริ', position: 'รองประธานกรรมการคนที่ 1', role_type: 'vice_president', image_url: '/assets/img/committee/committee-02-vice-president.webp', category: 'director', sort_order: 2, is_chairperson: false },
            { id: 103, name: 'นางพจวรรณ ธรรมเจริญ', position: 'รองประธานกรรมการคนที่ 2', role_type: 'vice_president', image_url: '/assets/img/committee/committee-03-vice-president-2.webp', category: 'director', sort_order: 3, is_chairperson: false },
            { id: 104, name: 'นายจัตุพล กันทะมูล', position: 'เหรัญญิก', role_type: 'treasurer', image_url: '/assets/img/committee/committee-04-treasurer.webp', category: 'director', sort_order: 4, is_chairperson: false }
          ],
          advisors: [
            { id: 120, name: 'นายสมศักดิ์ ที่ปรึกษา', position: 'ที่ปรึกษากิตติมศักดิ์', role_type: 'advisor', image_url: null, category: 'advisor', sort_order: 1, is_chairperson: false }
          ],
          auditors: [
            { id: 130, name: 'นางสมศรี ตรวจสอบ', position: 'ผู้ตรวจสอบกิจการ', role_type: 'auditor', image_url: null, category: 'auditor', sort_order: 1, is_chairperson: false }
          ],
          groups: [
            {
              id: 201,
              title: 'คณะกรรมการอำนวยการ',
              members: [
                { member_id: 101, name: 'นางสาวอุษา อิศรางกูร ณ อยุธยา', role_in_group: 'ประธานกรรมการ' },
                { member_id: 102, name: 'นางณาทัศญา ทรัพย์อักษรศิริ', role_in_group: 'กรรมการ' }
              ]
            }
          ]
        }
      });
    }

    if (pathname.includes('/selection-candidates') || pathname.includes('/selective-transfer/preview')) {
      return json(route, {
        success: true,
        data: {
          source_term: { id: 1, term_number: 38, term_name: 'ชุดที่ 38', status: 'published' },
          target_term_number: 39,
          target_term: null,
          candidates: {
            directors: [
              { id: 101, name: 'นางสาวอุษา อิศรางกูร ณ อยุธยา', position: 'ประธานกรรมการ', role_type: 'president', image_url: '/assets/img/committee/committee-01-president.webp', category: 'director', already_transferred: false },
              { id: 102, name: 'นางณาทัศญา ทรัพย์อักษรศิริ', position: 'รองประธานกรรมการคนที่ 1', role_type: 'vice_president', image_url: '/assets/img/committee/committee-02-vice-president.webp', category: 'director', already_transferred: false },
              { id: 103, name: 'นางพจวรรณ ธรรมเจริญ', position: 'รองประธานกรรมการคนที่ 2', role_type: 'vice_president', image_url: '/assets/img/committee/committee-03-vice-president-2.webp', category: 'director', already_transferred: false },
              { id: 104, name: 'นายจัตุพล กันทะมูล', position: 'เหรัญญิก', role_type: 'treasurer', image_url: '/assets/img/committee/committee-04-treasurer.webp', category: 'director', already_transferred: false }
            ],
            advisors: [
              { id: 120, name: 'นายสมศักดิ์ ที่ปรึกษา', position: 'ที่ปรึกษากิตติมศักดิ์', role_type: 'advisor', image_url: null, category: 'advisor', already_transferred: false }
            ],
            auditors: [
              { id: 130, name: 'นางสมศรี ตรวจสอบ', position: 'ผู้ตรวจสอบกิจการ', role_type: 'auditor', image_url: null, category: 'auditor', already_transferred: false }
            ]
          },
          groups: []
        }
      });
    }

    if (pathname.includes('/selective-transfer') && req.method() === 'POST') {
      transferredPayload = req.postDataJSON();
      return json(route, {
        success: true,
        message: 'ถ่ายโอนกรรมการ 3 ท่าน ไปยังชุดที่ 39 (ฉบับร่าง) สำเร็จแล้ว',
        data: {
          target_term_id: 2,
          target_term_number: 39,
          transferred_count: 3
        }
      });
    }

    if (pathname === '/api/admin/board-staff') {
      return json(route, {
        success: true,
        data: {
          boards: [],
          staff: []
        }
      });
    }

    return json(route, { success: true, data: [] });
  });

  await page.setViewportSize({ width: 1440, height: 950 });
  await page.goto('http://127.0.0.1:5204/__crud?kind=boardStaff', { waitUntil: 'domcontentloaded', timeout: 60000 });

  // 1. Wait for page header and term badges
  await page.waitForSelector('text=จัดการข้อมูลคณะกรรมการและเจ้าหน้าที่', { timeout: 15000 });
  await page.waitForSelector('text=เลือกกรรมการไปชุดที่ 39', { timeout: 10000 });

  // 2. Open selective transfer modal
  await page.click('button:has-text("เลือกกรรมการไปชุดที่ 39")');
  await page.waitForSelector('text=เลือกกรรมการจากชุดที่ 38 ไปยังชุดที่ 39 (ฉบับร่าง)', { timeout: 10000 });
  await page.waitForSelector('text=นางสาวอุษา อิศรางกูร ณ อยุธยา');

  // Screenshot 1: Wizard open with all candidates loaded
  await page.screenshot({ path: `${outDir}/01_wizard_open.png` });

  // 3. Select specific members and customize role
  // Click "ยกเลิกทั้งหมด" first
  await page.click('button:has-text("ยกเลิกทั้งหมด")');
  await page.waitForSelector('text=เลือกแล้ว 0 ท่าน');

  // Toggle selection on 2 members: 101 and 102
  await page.click('tr:has-text("นางสาวอุษา อิศรางกูร ณ อยุธยา") input[type="checkbox"]');
  await page.click('tr:has-text("นางณาทัศญา ทรัพย์อักษรศิริ") input[type="checkbox"]');
  await page.waitForSelector('text=เลือกแล้ว 2 ท่าน');

  // Customize position in row for 102
  const customInput = page.locator('tr:has-text("นางณาทัศญา ทรัพย์อักษรศิริ") input[type="text"]');
  await customInput.fill('รองประธานกรรมการ (วาระ 39)');

  // Screenshot 2: Customized position in Term 39
  await page.screenshot({ path: `${outDir}/02_customized_positions.png` });

  // 4. Confirm selective transfer
  await page.click('button:has-text("ยืนยันถ่ายโอน 2 ท่าน ไปชุดที่ 39 (ฉบับร่าง)")');

  // Wait for success toast / notification
  await page.waitForSelector('text=ถ่ายโอนกรรมการ 3 ท่าน ไปยังชุดที่ 39 (ฉบับร่าง) สำเร็จแล้ว', { timeout: 10000 });

  // Screenshot 3: Transfer confirmed
  await page.screenshot({ path: `${outDir}/03_transfer_confirmed.png` });

  assert.ok(transferredPayload !== null, 'Transfer payload was sent to server');
  assert.equal(transferredPayload.target_term_number, 39);
  assert.deepEqual(transferredPayload.selected_member_ids, [101, 102]);

  assert.deepEqual(errors, [], 'No uncaught page errors during selective transfer wizard UAT');
  console.log('PASS: Selective transfer wizard browser UAT passed and screenshots captured!');
} finally {
  await browser?.close();
  await server.close();
}
