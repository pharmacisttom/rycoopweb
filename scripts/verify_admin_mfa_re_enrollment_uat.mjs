import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

console.log('========================================================================');
console.log(' RYCOOPWEB — SECURE ADMIN MFA RE-ENROLLMENT BROWSER UAT TEST');
console.log('========================================================================');

const port = 5201;

const server = await createServer({
  configFile: false,
  appType: 'spa',
  cacheDir: 'storage/tmp/vite-admin-mfa-re-enroll-uat',
  plugins: [react()],
  resolve: { alias: { 'react-router-dom': 'react-router' } },
  server: { host: '127.0.0.1', port, strictPort: true },
  define: {
    'import.meta.env.VITE_FEATURE_MEMBER_PORTAL': JSON.stringify('false'),
    'import.meta.env.VITE_PUBLIC_STATIC_MODE': JSON.stringify('false')
  }
});

let browser;
let passed = 0;
let failed = 0;

function it(desc, cond) {
  if (cond) {
    console.log(` [PASS] ${desc}`);
    passed++;
  } else {
    console.log(` [FAIL] ${desc}`);
    failed++;
  }
}

try {
  await server.listen();
  console.log(` [VITE] Re-enrollment UAT server listening on http://127.0.0.1:${port}`);

  const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  browser = await chromium.launch({
    headless: true,
    executablePath: process.env.PLAYWRIGHT_EXECUTABLE || chromePath
  });

  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  // Mock authenticated super_admin session
  await page.route('**/api/auth/me', route => {
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        authenticated: true,
        user: {
          id: 1,
          name: 'ผู้ดูแลระบบสูงสุด',
          username: 'admin',
          email: 'admin@rayongcoop.com',
          role: 'super_admin',
          role_slug: 'super_admin',
          permissions: ['*']
        }
      })
    });
  });

  await page.route('**/csrf-token', route => {
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ token: 'mock-csrf-token' })
    });
  });

  // Mock /api/admin/security/mfa data
  let mfaRefreshCount = 0;
  await page.route('**/api/admin/security/mfa', route => {
    mfaRefreshCount++;
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        summary: {
          coverage_percent: 100,
          total_staff: 1,
          enrolled_staff: 1,
          pending_staff: 0
        },
        policy_note: 'การลงทะเบียน MFA จำเป็นสำหรับเจ้าหน้าที่ทุกตำแหน่ง',
        accounts: [
          {
            id: 1,
            user_id: 1,
            name: 'ผู้ดูแลระบบสูงสุด',
            username: 'admin',
            email: 'admin@rayongcoop.com',
            role_slug: 'super_admin',
            role_name: 'ผู้ดูแลระบบสูงสุด (Super Admin)',
            mfa_enabled: true,
            enrolled_at: '2026-11-06 20:00:00',
            recovery_codes_remaining: 7
          }
        ]
      })
    });
  });

  // Mock /api/auth/mfa/re-enroll endpoints
  let cancelCalled = false;
  await page.route('**/api/auth/mfa/re-enroll/start', async route => {
    const postData = JSON.parse(route.request().postData() || '{}');
    if (postData.password === 'WrongPassword!') {
      return route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          message: 'รหัสผ่านไม่ถูกต้อง ไม่สามารถเริ่มต้นเปลี่ยนอุปกรณ์ได้'
        })
      });
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        formatted_secret: 'JBSW Y3DP EHPK 3PXP JBSW Y3DP EHPK 3PXP',
        qr_code_uri: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="black"/></svg>',
        account: 'admin@rayongcoop.com',
        issuer: 'RayongCoop',
        expires_in: 600
      })
    });
  });

  await page.route('**/api/auth/mfa/re-enroll/cancel', route => {
    cancelCalled = true;
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, message: 'ยกเลิกการเปลี่ยนอุปกรณ์เรียบร้อยแล้ว' })
    });
  });

  await page.route('**/api/auth/mfa/re-enroll/confirm', async route => {
    const postData = JSON.parse(route.request().postData() || '{}');
    if (postData.code !== '123456') {
      return route.fulfill({
        status: 400,
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          message: 'รหัสความปลอดภัย 6 หลักไม่ถูกต้องหรือหมดอายุ'
        })
      });
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        message: 'เปลี่ยนอุปกรณ์ Authenticator และสร้างรหัสสำรองชุดใหม่สำเร็จเรียบร้อยแล้ว',
        recovery_codes: [
          'A1B2-C3D4-E5F6-0001',
          'A1B2-C3D4-E5F6-0002',
          'A1B2-C3D4-E5F6-0003',
          'A1B2-C3D4-E5F6-0004',
          'A1B2-C3D4-E5F6-0005',
          'A1B2-C3D4-E5F6-0006',
          'A1B2-C3D4-E5F6-0007',
          'A1B2-C3D4-E5F6-0008'
        ]
      })
    });
  });

  // Navigate to Admin Security MFA page
  await page.goto(`http://127.0.0.1:${port}/admin/security/mfa`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  // 1. Verify page title and "เปลี่ยนอุปกรณ์ Authenticator" button
  const pageHeader = await page.locator('h2.acc-card-title').textContent();
  it('1. /admin/security/mfa renders Staff MFA Enrollment Status table', pageHeader.includes('สถานะการลงทะเบียน MFA'));

  const btnReEnroll = await page.locator('#btn-re-enroll-mfa').count();
  it('2. Admin Security displays "เปลี่ยนอุปกรณ์ Authenticator" button', btnReEnroll === 1);

  // 2. Open re-enrollment modal (Step 1: Password Confirmation)
  await page.click('#btn-re-enroll-mfa');
  await page.waitForTimeout(300);

  const step1Text = await page.locator('.acc-modal-content').textContent();
  it('3. Modal opens at Step 1 requiring account password re-verification', step1Text.includes('ยืนยันรหัสผ่านบัญชีเพื่อความปลอดภัย'));

  // 3. Enter wrong password and verify rejection
  await page.fill('#re-enroll-password', 'WrongPassword!');
  await page.click('#btn-re-enroll-next');
  await page.waitForTimeout(500);

  const errorText = await page.locator('.acc-modal-content').textContent();
  it('4. Wrong password displays security error notice', errorText.includes('รหัสผ่านไม่ถูกต้อง'));

  // 4. Enter valid password and advance to Step 2 (QR code scan)
  await page.fill('#re-enroll-password', 'SecretAdmin123!');
  await page.click('#btn-re-enroll-next');
  await page.waitForTimeout(500);

  const step2Text = await page.locator('.acc-modal-content').textContent();
  const hasQrImage = await page.locator('#re-enroll-qr-image').count();
  it('5. Valid password advances to Step 2 with QR Code and Manual Secret display', step2Text.includes('สแกน QR Code ด้วยอุปกรณ์ใหม่') && hasQrImage === 1);

  // 5. Submit wrong 6-digit TOTP code
  await page.fill('#re-enroll-otp', '000000');
  await page.click('#btn-re-enroll-confirm');
  await page.waitForTimeout(500);

  const otpError = await page.locator('.acc-modal-content').textContent();
  it('6. Invalid TOTP code displays error and refuses activation', otpError.includes('ไม่ถูกต้องหรือหมดอายุ'));

  // 6. Submit valid 6-digit TOTP code -> advance to Step 3 (Success & Recovery Codes)
  await page.fill('#re-enroll-otp', '123456');
  await page.click('#btn-re-enroll-confirm');
  await page.waitForTimeout(500);

  const step3Text = await page.locator('.acc-modal-content').textContent();
  it('7. Valid TOTP activates new secret and advances to Step 3 (Success)', step3Text.includes('เปลี่ยนอุปกรณ์ Authenticator สำเร็จ'));

  // 7. Verify 8 recovery codes are displayed
  const codeElements = await page.locator('.acc-modal-content div:text-matches("A1B2-C3D4-E5F6")').count();
  it('8. Step 3 renders 8 new single-use recovery code pills', codeElements === 8);

  // 8. Test copy recovery codes button
  await page.click('button:has-text("คัดลอกรหัสทั้งหมด")');
  await page.waitForTimeout(300);
  const copyBtnText = await page.locator('button:has-text("คัดลอกแล้ว!")').count();
  it('9. "คัดลอกรหัสทั้งหมด" feedback triggers successfully', copyBtnText === 1);

  // 9. Finish workflow and verify modal closes and table refreshes
  await page.click('#btn-re-enroll-finish');
  await page.waitForTimeout(400);

  const modalOpen = await page.locator('.acc-modal-overlay').count();
  it('10. Finish closes re-enrollment modal and refreshes MFA administration', modalOpen === 0 && mfaRefreshCount >= 2);

  console.log('========================================================================');
  console.log(` RESULTS: ${passed} PASSED, ${failed} FAILED`);
  console.log('========================================================================');

  if (failed > 0) process.exit(1);

} finally {
  if (browser) await browser.close();
  await server.close();
}
