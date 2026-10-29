import assert from 'node:assert/strict';
import fs from 'node:fs';
import crypto from 'node:crypto';
const asset = fs.readFileSync('src/assets/branding/coop-logo.webp');
assert.equal(asset.toString('ascii', 0, 4), 'RIFF');assert.equal(asset.toString('ascii', 8, 12), 'WEBP');
assert.equal(crypto.createHash('sha256').update(asset).digest('hex'), '326184bb4128843e5ee639732207de3781aa3052c737bde434cf8de7f18b08ce');
const files = ['src/components/layout/Navbar.jsx', 'src/components/layout/Footer.jsx', 'src/components/member/AdminMemberShell.jsx',
  'src/components/member/AuthModal.jsx', 'src/components/admin/AdminControlCenterShell.jsx', 'src/components/auth/MfaAuthShell.jsx',
  'src/components/common/PageSkeletonLoader.jsx', 'src/pages/LoginPage.jsx', 'src/components/dashboard/DashboardHeader.jsx'];
for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');assert.ok(source.includes('<CoopBrandLogo') || (file.endsWith('AdminMemberShell.jsx') && source.includes('<AdminAppShell')), `${file} uses shared brand`);
  assert.ok(!/\/assets\/(?:img\/logo|images\/logo)|\/img\/logo|logo-C7DMvEhb/.test(source), `${file} has no legacy runtime path`);
}
const built = fs.readdirSync('public/app/assets').filter(file => /^coop-logo-.*\.webp$/.test(file));
assert.equal(built.length, 1);assert.deepEqual(fs.readFileSync(`public/app/assets/${built[0]}`), asset);
assert.ok(fs.readFileSync('public/app/index.html', 'utf8').includes(`/app/assets/${built[0]}`), 'favicon is packaged by Vite');
const css = fs.readFileSync('src/components/branding/CoopBrandLogo.css', 'utf8');assert.ok(css.includes('object-fit:contain'));
const component = fs.readFileSync('src/components/branding/CoopBrandLogo.jsx', 'utf8');
assert.ok(!/currentTarget\.src\s*=|target\.src\s*=/.test(component), 'no recursive image retry');
console.log('PASS: verified official asset checksum, 9 shared brand integrations, reproducible emitted asset, favicon and no fallback loop');
