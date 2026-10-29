import assert from 'node:assert/strict';
import fs from 'node:fs';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const mockPlugin = {
  name: 'mfa-ui-test-mocks',
  enforce: 'pre',
  load(id) {
    const path = id.replaceAll('\\', '/');
    if (path.endsWith('/src/context/AuthContext.jsx')) {
      return 'export const useAuth = () => globalThis.__mfaTestAuth || { user: null, isLoggedIn: false, sessionStatus: "mfa-verify", refreshSession: async () => {}, logout: async () => {} };';
    }
  },
};

const server = await createServer({
  configFile: false,cacheDir:'storage/tmp/vite-mfa-ui-tests',
  plugins: [mockPlugin, react()],
  resolve: { alias: { 'react-router-dom': 'react-router' } },
  optimizeDeps: { noDiscovery: true, include: [] },
  server: { middlewareMode: true },
  appType: 'custom',
});

try {
  const { StaticRouter } = await server.ssrLoadModule('react-router');
  const {
    default: OtpInput,
    sanitizeOtpDigits,
    isValidSixDigitOtp,
    OTP_LENGTH,
  } = await server.ssrLoadModule('/src/components/auth/OtpInput.jsx');
  const {
    default: MfaAuthShell,
    formatMfaCountdown,
  } = await server.ssrLoadModule('/src/components/auth/MfaAuthShell.jsx');
  const {
    default: MfaPage,
    sanitizeRecoveryCodeInput,
    resolveSafeMfaError,
  } = await server.ssrLoadModule('/src/pages/MfaPage.jsx');

  // 1. Six-digit OTP validation tests
  assert.equal(OTP_LENGTH, 6, 'OTP length constant must be 6');
  assert.equal(isValidSixDigitOtp('123456'), true, 'Exact 6 numeric digits accepted');
  assert.equal(isValidSixDigitOtp('000000'), true, 'Leading zeros accepted');
  assert.equal(isValidSixDigitOtp('12345'), false, '5 digits rejected');
  assert.equal(isValidSixDigitOtp('1234567'), false, '7 digits rejected');
  assert.equal(isValidSixDigitOtp('12345a'), false, 'Alpha character rejected');
  assert.equal(isValidSixDigitOtp('123 45'), false, 'Whitespace rejected');
  console.log('PASS: 1. Six-digit OTP validation');

  // 2. Paste handling & sanitization tests
  assert.equal(sanitizeOtpDigits('123 456'), '123456', 'Spaces stripped on paste');
  assert.equal(sanitizeOtpDigits('12-34-56'), '123456', 'Hyphens stripped on paste');
  assert.equal(sanitizeOtpDigits('OTP: 987654 (valid)'), '987654', 'Prefix/suffix text stripped and capped at 6 digits');
  assert.equal(sanitizeOtpDigits('1234567890'), '123456', 'Truncates pasted strings longer than 6 digits');
  assert.equal(sanitizeOtpDigits('abcdef'), '', 'Non-digits produce empty string');
  console.log('PASS: 2. OTP paste sanitization');

  // 3. OtpInput accessibility & 6 visual slots rendering
  const otpHtml = renderToStaticMarkup(
    React.createElement(OtpInput, {
      id: 'mfa-code',
      value: '482',
      invalid: true,
      describedBy: 'mfa-error-message mfa-otp-hint',
      label: 'รหัสยืนยัน 6 หลัก',
    })
  );
  assert.ok(otpHtml.includes('id="mfa-code"'), 'Single accessible input #mfa-code rendered');
  assert.ok(otpHtml.includes('inputMode="numeric"'), 'Numeric inputMode present');
  assert.ok(otpHtml.includes('autoComplete="one-time-code"'), 'Mobile autofill one-time-code present');
  assert.ok(otpHtml.includes('pattern="[0-9]{6}"'), 'Exact 6-digit pattern present');
  assert.ok(otpHtml.includes('aria-invalid="true"'), 'Invalid state communicated to screen readers');
  assert.ok(otpHtml.includes('aria-describedby="mfa-error-message mfa-otp-hint"'), 'Error association via aria-describedby');
  for (let i = 0; i < 6; i++) {
    assert.ok(otpHtml.includes(`data-slot-index="${i}"`), `Visual slot ${i} rendered`);
  }
  assert.ok(otpHtml.includes('3/6 หลัก'), 'Live progress indicator rendered');
  console.log('PASS: 3. Accessible 6-slot OTP component & ARIA attributes');

  // 4. Authoritative Server Session Expiry formatting & states
  assert.equal(formatMfaCountdown(600), '10:00', '600s -> 10:00');
  assert.equal(formatMfaCountdown(119), '01:59', '119s -> 01:59');
  assert.equal(formatMfaCountdown(0), '00:00', '0s -> 00:00');
  assert.equal(formatMfaCountdown(null), null, 'null -> null (before server response)');

  const warningShellHtml = renderToStaticMarkup(
    React.createElement(
      MfaAuthShell,
      { remainingSeconds: 95, isExpired: false, userEmail: 'admin@rayongcoop.org' },
      React.createElement('div', null, 'Body')
    )
  );
  assert.ok(warningShellHtml.includes('mfa-expiry-bar warning'), 'Warning state shown when <= 120s remaining');
  assert.ok(warningShellHtml.includes('01:35 นาที'), 'Exact server countdown rendered');
  assert.ok(warningShellHtml.includes('admin@rayongcoop.org'), 'Authenticated pending user email shown');

  const expiredShellHtml = renderToStaticMarkup(
    React.createElement(
      MfaAuthShell,
      { remainingSeconds: 0, isExpired: true },
      React.createElement('div', null, 'Body')
    )
  );
  assert.ok(expiredShellHtml.includes('mfa-expiry-bar expired'), 'Expired state shown when 0s remaining');
  assert.ok(expiredShellHtml.includes('เซสชันการยืนยันตัวตนหมดอายุแล้ว'), 'Expired Thai message displayed');
  console.log('PASS: 4. Authoritative server session expiry UX (normal, warning, expired)');

  // 5. HTTP 400 / 401 / 403 / 422 / 429 / 500 error handling & stack-trace redaction
  const err400 = resolveSafeMfaError({ status: 400, message: 'รหัสความปลอดภัย 6 หลักไม่ถูกต้องหรือหมดอายุ' }, false);
  assert.equal(err400.status, 400);
  assert.equal(err400.expired, false);
  assert.ok(err400.message.includes('ไม่ถูกต้อง'));

  const err401 = resolveSafeMfaError({ status: 401, message: 'Unauthorized' }, false);
  assert.equal(err401.status, 401);
  assert.equal(err401.expired, true);

  const err403Challenge = resolveSafeMfaError({ status: 403, code: 'MFA_ENROLLMENT_REQUIRED', message: 'Forbidden' }, false);
  assert.equal(err403Challenge.redirectMode, 'enroll', '403 MFA_ENROLLMENT_REQUIRED redirects cleanly without false error');

  const err403Csrf = resolveSafeMfaError({ status: 403, message: 'CSRF token mismatch' }, false);
  assert.equal(err403Csrf.status, 403);
  assert.ok(err403Csrf.message.includes('CSRF'));

  const err422 = resolveSafeMfaError({ status: 422, message: '' }, false);
  assert.equal(err422.status, 422);
  assert.ok(err422.message.includes('6 หลัก'));

  const err429 = resolveSafeMfaError({ status: 429, message: '' }, false);
  assert.equal(err429.status, 429);
  assert.ok(err429.message.includes('5 นาที'));

  const err500Leak = resolveSafeMfaError({
    status: 500,
    message: 'PDOException: SQLSTATE[42S02] in /var/www/rycoopweb/app/Controllers/Admin/MfaController.php:240 Stack trace: #0',
  }, false);
  assert.equal(err500Leak.status, 500);
  assert.ok(!err500Leak.message.includes('PDOException'), 'Stack trace never leaked to user');
  assert.ok(!err500Leak.message.includes('/var/www'), 'Server path never leaked to user');
  assert.ok(err500Leak.message.includes('ขัดข้องชั่วคราว'));
  console.log('PASS: 5. HTTP 400/401/403/422/429/500 UX error handling & stack-trace suppression');

  // 6. Recovery code input normalization & storage hygiene
  assert.equal(sanitizeRecoveryCodeInput('abcd1234-ef567890'), 'ABCD1234-EF567890');
  assert.equal(sanitizeRecoveryCodeInput('abcd 1234!@#$'), 'ABCD1234');
  const mfaPageSrc = fs.readFileSync('src/pages/MfaPage.jsx', 'utf8');
  const otpInputSrc = fs.readFileSync('src/components/auth/OtpInput.jsx', 'utf8');
  assert.ok(!mfaPageSrc.includes('localStorage') && !mfaPageSrc.includes('sessionStorage'), 'MfaPage never persists OTP or recovery codes in browser storage');
  assert.ok(!otpInputSrc.includes('localStorage') && !otpInputSrc.includes('sessionStorage'), 'OtpInput never persists OTP in browser storage');
  assert.ok(!mfaPageSrc.includes('console.log') && !otpInputSrc.includes('console.log'), 'No OTP or secret console logging');
  console.log('PASS: 6. Recovery code normalization & zero browser-storage persistence');

  // 7. Verify & Enroll page structure, branding, and layout isolation
  const verifyHtml = renderToStaticMarkup(
    React.createElement(StaticRouter, { location: '/admin/mfa/verify' }, React.createElement(MfaPage, { mode: 'verify' }))
  );
  assert.ok(verifyHtml.includes('coop-brand-logo') && verifyHtml.includes('coop-logo.webp'), 'Official cooperative logo rendered');
  assert.ok(verifyHtml.includes('สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด'), 'Cooperative Thai name rendered');
  assert.ok(verifyHtml.includes('RYCOOPWEB Secure Login'), 'RYCOOPWEB Secure Login header tag rendered');
  assert.ok(verifyHtml.includes('ยืนยันตัวตนสองขั้นตอน'), 'Verify Thai title rendered');
  assert.ok(verifyHtml.includes('Two-Factor Authentication'), 'English subtitle rendered');
  assert.ok(verifyHtml.includes('กรอกรหัสยืนยัน 6 หลักจากแอป Authenticator ของคุณ'), 'OTP instruction rendered');
  assert.ok(verifyHtml.includes('เริ่มเข้าสู่ระบบใหม่'), 'Restart login button rendered');
  assert.ok(verifyHtml.includes('ระบบรักษาความปลอดภัยสำหรับเจ้าหน้าที่สหกรณ์'), 'Security footer rendered');
  assert.ok(!verifyHtml.includes('ADMIN_SIDEBAR_GROUPS'), 'Admin sidebar isolated from MFA route');

  const enrollHtml = renderToStaticMarkup(
    React.createElement(StaticRouter, { location: '/admin/mfa/enroll' }, React.createElement(MfaPage, { mode: 'enroll' }))
  );
  assert.ok(enrollHtml.includes('ตั้งค่าการยืนยันตัวตนสองขั้นตอน'), 'Enroll Thai title rendered');
  console.log('PASS: 7. Verify & Enroll page structure, branding, and layout isolation');

  if (process.env.MFA_PREVIEW_OUT) {
    const css = fs.readFileSync('src/pages/MfaPage.css', 'utf8');
    const logoB64 = fs.readFileSync('public/assets/img/logo.webp').toString('base64');
    const previewShell = renderToStaticMarkup(
      React.createElement(
        MfaAuthShell,
        {
          title: 'ยืนยันตัวตนสองขั้นตอน',
          subtitle: 'Two-Factor Authentication',
          instruction: 'กรอกรหัสยืนยัน 6 หลักจากแอป Authenticator ของคุณ',
          remainingSeconds: 542,
          isExpired: false,
          userEmail: 'admin@rayongcoop.org',
        },
        React.createElement(
          'form',
          null,
          React.createElement(OtpInput, {
            id: 'mfa-code',
            value: '4829',
            label: 'รหัสยืนยัน 6 หลัก',
          }),
          React.createElement('button', { type: 'button', className: 'mfa-btn-primary' }, 'ยืนยันและเข้าสู่ระบบ')
        ),
        React.createElement(
          'div',
          { className: 'mfa-secondary-actions' },
          React.createElement('button', { type: 'button', className: 'mfa-btn-secondary' }, 'ใช้รหัสสำรอง'),
          React.createElement('button', { type: 'button', className: 'mfa-btn-ghost' }, 'เริ่มเข้าสู่ระบบใหม่')
        )
      )
    ).replace('/assets/img/logo.webp', `data:image/webp;base64,${logoB64}`);
    fs.writeFileSync(
      process.env.MFA_PREVIEW_OUT,
      `<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}${css}</style></head><body>${previewShell}</body></html>`
    );
  }

  console.log('ALL MFA UI/UX REDESIGN TESTS PASSED');
} finally {
  await server.close();
}

