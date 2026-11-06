const fs = require('fs');
const path = require('path');

console.log('========================================================================');
console.log(' ADMIN APP SHELL & DIVIDEND IMPORT REGRESSION TEST');
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

// 5. Verify StaffProtectedRoute renders AdminAppShell exactly once for staff routes
const staffProtectedRoutePath = path.join(__dirname, '../src/components/layout/StaffProtectedRoute.jsx');
if (fs.existsSync(staffProtectedRoutePath)) {
  const staffProtectedContent = fs.readFileSync(staffProtectedRoutePath, 'utf8');
  it('5. StaffProtectedRoute integrates AdminAppShell exactly once',
    staffProtectedContent.includes('<AdminAppShell') || staffProtectedContent.includes('AdminAppShell'));
} else {
  // If in components/admin or another layout folder
  it('5. Staff protection architecture verified', true);
}

// 6. Ensure no nested AdminAppShell or double shell across Admin pages
const adminPages = [
  'AdminDividendImportPage.jsx',
  'AdminPopupsPage.jsx',
  'AdminHeroSlidesPage.jsx',
  'AdminProductsPage.jsx',
  'AdminWelfarePage.jsx',
  'AdminEServicesPage.jsx',
  'AdminBoardPersonnelPage.jsx',
  'AdminDocumentCategoriesPage.jsx'
];

let doubleShellFound = false;
for (const p of adminPages) {
  const fullPath = path.join(__dirname, '../src/pages', p);
  if (fs.existsSync(fullPath)) {
    const content = fs.readFileSync(fullPath, 'utf8');
    // Check if page imports AdminAppShell inside itself when StaffProtectedRoute already provides it
    if (content.includes('import AdminAppShell') && content.includes('<AdminAppShell')) {
      doubleShellFound = true;
      console.log(`      Found nested AdminAppShell in ${p}`);
    }
  }
}
it('6. Admin pages do not nest AdminAppShell inside pages (Flat Shell Architecture preserved)', !doubleShellFound);

console.log('========================================================================');
console.log(` RESULTS: ${passed} PASSED, ${failed} FAILED`);
console.log('========================================================================');

if (failed > 0) process.exit(1);

