import fs from 'node:fs';
import assert from 'node:assert/strict';

const app = fs.readFileSync(new URL('../src/App.jsx', import.meta.url), 'utf8');
const cookie = fs.readFileSync(new URL('../src/components/common/CookieConsent.jsx', import.meta.url), 'utf8');
const css = fs.readFileSync(new URL('../src/index.css', import.meta.url), 'utf8');
const toast = fs.readFileSync(new URL('../src/context/ToastContext.jsx', import.meta.url), 'utf8');
const swal = fs.readFileSync(new URL('../src/utils/swal.js', import.meta.url), 'utf8');

assert.match(app, /<CookieConsent\s*\/>/);
assert.doesNotMatch(app, /!memberAdminPage\s*&&\s*<>[^\n]*<CookieConsent\s*\/>/);
assert.match(cookie, /role="dialog"\s+aria-modal="true"/);
assert.match(cookie, /id="rycoop-cookie-title"/);
assert.match(cookie, /!read\(RECEIPT_KEY\)/);
assert.match(cookie, /\/api\/cookie-consent/);
assert.match(cookie, /response\.ok\s*\|\|\s*result\.success/);
assert.match(css, /\.cookie-consent\s*\{[^}]*position:\s*fixed;[^}]*z-index:\s*11000;/s);
assert.match(toast, /void swalToast\(/);
assert.match(swal, /import Swal from 'sweetalert2'/);
assert.match(swal, /export function showToast\(/);
console.log('COOKIE_MODAL_AND_SWEETALERT2_CONTRACT=PASS');
