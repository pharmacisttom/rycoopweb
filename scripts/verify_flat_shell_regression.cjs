const fs = require('fs');
const path = require('path');

console.log('========================================================================');
console.log(' ADMIN APP SHELL & DIVIDEND IMPORT REGRESSION TEST (SPRINT 1B)');
console.log('========================================================================');

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

// 1. Verify AdminDividendImportPage.jsx does not import AdminMemberShell
const dividendImportPath = path.join(__dirname, '../src/pages/AdminDividendImportPage.jsx');
const dividendImportContent = fs.readFileSync(dividendImportPath, 'utf8');

it('1. AdminDividendImportPage does NOT import AdminMemberShell', 
  !dividendImportContent.includes("import AdminMemberShell") && 
  !dividendImportContent.includes("<AdminMemberShell"));

it('2. AdminDividendImportPage renders flat section.member-admin', 
  dividendImportContent.includes('<section className="member-admin">') &&
  dividendImportContent.includes('</section>;'));

it('3. AdminDividendImportPage preserves CSRF, form and confirmation workflow',
  dividendImportContent.includes("body.append('_csrf_token', token)") &&
  dividendImportContent.includes("/api/admin/dividends/") &&
  dividendImportContent.includes("maskIdCard") &&
  dividendImportContent.includes("maskName"));

// 4. Verify App.jsx route configuration for /admin/dividends/import
const appPath = path.join(__dirname, '../src/App.jsx');
const appContent = fs.readFileSync(appPath, 'utf8');

it('4. App.jsx route /admin/dividends/import uses StaffProtectedRoute without outer shell wrapper',
  appContent.includes('path="/admin/dividends/import" element={<StaffProtectedRoute permission="dividends.import"><AdminDividendImportPage /></StaffProtectedRoute>}'));

// 5. Verify actual StaffProtectedRoute in src/App.jsx renders AdminAppShell exactly once for staff routes
const staffProtectedRouteRegex = /function StaffProtectedRoute\([\s\S]*?return\s+<AdminActionProvider><AdminAppShell[\s\S]*?>\{children\}<\/AdminAppShell><\/AdminActionProvider>;/m;
const staffProtectionVerified = staffProtectedRouteRegex.test(appContent);

it('5. StaffProtectedRoute in src/App.jsx renders AdminAppShell exactly once with AdminActionProvider',
  staffProtectionVerified);

// 6. Verify AdminLayout.jsx delegates directly to canonical AdminControlCenterShell
const adminLayoutPath = path.join(__dirname, '../src/components/admin/design-system/AdminLayout.jsx');
const adminLayoutContent = fs.readFileSync(adminLayoutPath, 'utf8');
it('6. AdminLayout.jsx delegates directly to canonical AdminControlCenterShell (Single Shell Rule)',
  adminLayoutContent.includes('import AdminControlCenterShell from') &&
  adminLayoutContent.includes('<AdminControlCenterShell'));

// 7. Ensure no nested AdminAppShell, AdminMemberShell or double shell across Admin pages
const adminPages = [
  'AdminDividendImportPage.jsx',
  'AdminPopupsPage.jsx',
  'AdminHeroSlidesPage.jsx',
  'AdminProductsPage.jsx',
  'AdminWelfarePage.jsx',
  'AdminEServicesPage.jsx',
  'AdminBoardPersonnelPage.jsx',
  'AdminDocumentCategoriesPage.jsx',
  'AdminDashboardPage.jsx',
  'AdminWorkspacePage.jsx'
];

let doubleShellFound = false;
for (const p of adminPages) {
  const fullPath = path.join(__dirname, '../src/pages', p);
  if (fs.existsSync(fullPath)) {
    const content = fs.readFileSync(fullPath, 'utf8');
    if ((content.includes('import AdminAppShell') && content.includes('<AdminAppShell')) ||
        (content.includes('import AdminMemberShell') && content.includes('<AdminMemberShell'))) {
      doubleShellFound = true;
      console.log(`      Found nested shell in ${p}`);
    }
  }
}
it('7. Admin pages do not nest AdminAppShell or AdminMemberShell (Flat Shell Architecture preserved)', !doubleShellFound);

console.log('========================================================================');
console.log(` RESULTS: ${passed} PASSED, ${failed} FAILED`);
console.log('========================================================================');

if (failed > 0) process.exit(1);
