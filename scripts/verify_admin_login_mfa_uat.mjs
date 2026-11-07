import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

console.log('========================================================================');
console.log(' RYCOOPWEB — ADMIN LOGIN & TOTP MFA BROWSER UAT TEST');
console.log('========================================================================');

const port = 5199;

// Mock server for testing frontend routing and auth state transitions
const server = await createServer({
  configFile: false,
  appType: 'spa',
  cacheDir: 'storage/tmp/vite-admin-auth-uat',
  plugins: [react()],
  resolve: { alias: { 'react-router-dom': 'react-router' } },
  server: { host: '127.0.0.1', port, strictPort: true },
  define: {
    'import.meta.env.VITE_FEATURE_MEMBER_PORTAL': JSON.stringify('false'),
    'import.meta.env.VITE_PUBLIC_STATIC_MODE': JSON.stringify('true')
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
  console.log(` [VITE] Auth UAT test server listening on http://127.0.0.1:${port}`);

  const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  browser = await chromium.launch({
    headless: true,
    executablePath: process.env.PLAYWRIGHT_EXECUTABLE || chromePath
  });

  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  // 1. Verify /admin/login renders when VITE_FEATURE_MEMBER_PORTAL=false
  await page.goto(`http://127.0.0.1:${port}/admin/login`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const titleText = await page.locator('h2#member-login-title').textContent();
  it('1. /admin/login renders Staff Login header', titleText.includes('เข้าสู่ระบบเจ้าหน้าที่'));

  const usernameInput = await page.locator('input#login-username').count();
  const passwordInput = await page.locator('input#login-password').count();
  it('2. /admin/login renders username and password input fields', usernameInput === 1 && passwordInput === 1);

  // 3. Verify /member/login renders ServiceUnavailablePage when VITE_FEATURE_MEMBER_PORTAL=false
  await page.goto(`http://127.0.0.1:${port}/member/login`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const unavailableHeader = await page.locator('h1, h2').allTextContents();
  const isUnavailable = unavailableHeader.some(t => t.includes('ยังไม่เปิดให้บริการ') || t.includes('ไม่พร้อมใช้งาน') || t.includes('503'));
  it('3. /member/login is blocked and displays ServiceUnavailablePage', isUnavailable);

  // 4. Verify /login renders ServiceUnavailablePage when VITE_FEATURE_MEMBER_PORTAL=false
  await page.goto(`http://127.0.0.1:${port}/login`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const isGeneralLoginUnavailable = (await page.locator('h1, h2').allTextContents()).some(t => t.includes('ยังไม่เปิดให้บริการ') || t.includes('ไม่พร้อมใช้งาน') || t.includes('503'));
  it('4. /login is blocked and displays ServiceUnavailablePage', isGeneralLoginUnavailable);

  // 5. Test staff login submission and MFA challenge redirection
  let mfaStatusRequested = false;
  await page.route('**/csrf-token', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ token: 'test-csrf-token' }) }));
  await page.route('**/login', route => {
    if (route.request().method() === 'POST') {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          mfa_required: true,
          redirect: '/admin/mfa/verify',
          message: 'กรุณายืนยันรหัสความปลอดภัย 2FA'
        })
      });
    }
    return route.continue();
  });
  await page.route('**/api/auth/mfa/status', route => {
    mfaStatusRequested = true;
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        enrolled: true,
        pending_verification: true,
        challenge_expires_at: Math.floor(Date.now() / 1000) + 600,
        remaining_seconds: 600,
        user: { id: 1, name: 'ผู้ดูแลระบบ', email: 'admin@example.invalid' }
      })
    });
  });

  await page.goto(`http://127.0.0.1:${port}/admin/login`, { waitUntil: 'networkidle' });
  await page.fill('input#login-username', 'admin');
  await page.fill('input#login-password', 'SecretAdmin123!');
  await page.click('button.member-entry-submit');

  await page.waitForURL(`**/admin/mfa/verify`, { timeout: 10000 });
  it('5. Staff login submits credentials and redirects to /admin/mfa/verify', page.url().includes('/admin/mfa/verify'));

  // Wait for MFA verification page to mount and load session status
  await page.waitForSelector('input#mfa-code', { timeout: 10000 });
  it('6. MfaPage queries /api/auth/mfa/status to load authoritative session state', mfaStatusRequested);

  // 7. Verify MFA verify page renders with 6 visual OTP slots
  const otpSlotsCount = await page.locator('.mfa-otp-slot').count();
  it('7. MFA verification page renders 6 visual OTP slot boxes', otpSlotsCount === 6);

  // 8. Test invalid OTP verification rejection
  await page.route('**/api/auth/mfa/verify', route => route.fulfill({
    status: 400,
    contentType: 'application/json',
    body: JSON.stringify({ success: false, message: 'รหัสความปลอดภัย 6 หลักไม่ถูกต้องหรือหมดอายุ' })
  }));

  await page.fill('input#mfa-code', '000000');
  await page.click('button.mfa-btn-primary');
  await page.waitForSelector('#mfa-error-message', { timeout: 5000 });

  const errorNotice = await page.locator('#mfa-error-message').textContent();
  it('8. Invalid TOTP code shows security error and does not advance', errorNotice.includes('ไม่ถูกต้อง'));

  // 9. Test valid OTP verification success and session refresh
  let meEndpointCalled = false;
  await page.route('**/api/auth/mfa/verify', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ success: true, redirect: '/admin/workspace' })
  }));
  await page.route('**/api/auth/me', route => {
    meEndpointCalled = true;
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        authenticated: true,
        user: {
          id: 1,
          name: 'เภสัชกร ทอม',
          username: 'admin',
          email: 'admin@rayongcoop.com',
          role: 'super_admin',
          role_slug: 'super_admin',
          permissions: ['*']
        }
      })
    });
  });

  // Re-enter valid digits and submit
  await page.fill('input#mfa-code', '123456');
  await page.click('button.mfa-btn-primary');
  await page.waitForTimeout(1000);

  it('9. Valid TOTP code verifies and triggers /api/auth/me session refresh', meEndpointCalled);

  console.log('========================================================================');
  console.log(` RESULTS: ${passed} PASSED, ${failed} FAILED`);
  console.log('========================================================================');

  if (failed > 0) process.exit(1);

} finally {
  if (browser) await browser.close();
  await server.close();
}
