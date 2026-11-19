import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL('../' + path, import.meta.url), 'utf8');

const routes = read('config/routes.php');
const docsPage = read('src/pages/DocumentsPage.jsx');
const publicDocs = read('app/Controllers/Public/DocumentController.php');
const receiptPage = read('src/pages/VerifyReceiptPage.jsx');
const memberPage = read('src/pages/MemberDashboardPage.jsx');
const adminPage = read('src/pages/AdminDashboardPage.jsx');
const home = read('src/pages/HomePage.jsx');
const calc = read('src/components/calculators/LoanCalculator.jsx');

assert.match(routes, /\/api\/public\/documents\/\{id\}\/download/);
assert.match(docsPage, /\/api\/public\/documents\/\$\{encodeURIComponent\(doc.id\)\}\/download/);
assert.match(publicDocs, /document_categories c ON c\.id=d\.category_id/);
assert.match(publicDocs, /http_response_code\(404\)/);
assert.match(publicDocs, /storage_upload_path\(/);
assert.doesNotMatch(receiptPage, /sampleReceipt|setVerified\(true\)|Valid Digital Signature/);
assert.doesNotMatch(memberPage, /MEMBER_COMPLAINTS|LN-6703-01|localStorage\.setItem\('coop_service_requests'/);
assert.doesNotMatch(adminPage, /useState\(ANNOUNCEMENTS\)|useState\(LOAN_PRODUCTS\)/);
assert.match(home, /INTEREST_RATES\?\.depositSource\?\.url/);
assert.match(calc, /if \(!activeProduct\)/);
for (const page of [
  'src/pages/PrivacyPolicyPage.jsx',
  'src/components/member/AuthModal.jsx',
  'src/pages/AdminDashboardPage.jsx',
  'src/pages/MemberDashboardPage.jsx',
]) {
  assert.doesNotMatch(read(page), /from ['"][^'"]*data\/mockData['"]/);
}
console.log('RC03_PRODUCTION_CONTENT_DOWNLOAD_GUARDS=PASS');
